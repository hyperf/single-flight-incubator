<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
require_once __DIR__ . '/../vendor/autoload.php';

use Hyperf\Incubator\WorkerPool\Config;
use Hyperf\Incubator\WorkerPool\WorkerPool;

use function Swoole\Coroutine\run;

run(static function (): void {
    echo 'WorkerPool benchmarks', \PHP_EOL, \PHP_EOL;

    // ---- 1) sync submit throughput ----
    $config = (new Config())->setCapacity(10)->setPreSpawn(true);
    $pool = new WorkerPool($config);
    $rounds = 20000;
    $at = microtime(true);
    for ($i = 0; $i < $rounds; ++$i) {
        $pool->submit(static fn () => null, sync: true);
    }
    printf("[1] 同步任务吞吐 (cap 10, %d 个任务): %d tasks/s\n", $rounds, (int) ($rounds / (microtime(true) - $at)));
    $pool->stop();

    // ---- 2) throughput with the resident GC enabled ----
    $config = (new Config())->setCapacity(20)->setPreSpawn(true)->setGcIntervalMs(150);
    $pool = new WorkerPool($config);
    $rounds = 5000;
    $at = microtime(true);
    for ($i = 0; $i < $rounds; ++$i) {
        $pool->submit(static fn () => null, sync: true);
    }
    printf("[2] GC常驻时吞吐 (cap 20, %d 个任务): %d tasks/s\n", $rounds, (int) ($rounds / (microtime(true) - $at)));
    $pool->stop();

    // ---- 3) high idle churn: batches of async tasks, all workers idle in between ----
    $config = (new Config())->setCapacity(50)->setPreSpawn(true);
    $pool = new WorkerPool($config);
    $rounds = 10000;
    $at = microtime(true);
    for ($batch = 0; $batch < 200; ++$batch) {
        for ($i = 0; $i < 50; ++$i) {
            $pool->submit(static fn () => usleep(100));
        }
        usleep(20 * 1000);
    }
    printf("[3] 高空闲流转吞吐 (cap 50, %d 个任务): %d tasks/s\n", $rounds, (int) ($rounds / (microtime(true) - $at)));
    $pool->stop();

    // ---- 4) memory: pool create/task/GC/stop cycles ----
    $before = memory_get_usage(true);
    for ($round = 0; $round < 50; ++$round) {
        $config = (new Config())->setCapacity(10)->setPreSpawn(true)->setGcIntervalMs(100);
        $pool = new WorkerPool($config);
        for ($i = 0; $i < 20; ++$i) {
            $pool->submit(static fn () => null, sync: true);
        }
        usleep(200 * 1000);
        $pool->stop();
    }
    printf("[4] 内存: 50个池 忙闲+全量回收周期: 增量 %d bytes\n", memory_get_usage(true) - $before);

    // ---- 5) memory: one long-running pool with repeated full collections ----
    $config = (new Config())->setCapacity(30)->setPreSpawn(true)->setGcIntervalMs(100);
    $pool = new WorkerPool($config);
    $before = memory_get_usage(true);
    for ($round = 0; $round < 50; ++$round) {
        for ($i = 0; $i < 20; ++$i) {
            $pool->submit(static fn () => usleep(200));
        }
        usleep(250 * 1000);
        for ($i = 0; $i < 5; ++$i) {
            $pool->submit(static fn () => usleep(100), sync: true);
        }
    }
    printf("[5] 内存: 50轮 忙闲交替+全量回收+按需再生: 增量 %d bytes\n", memory_get_usage(true) - $before);
    $pool->stop();
});
