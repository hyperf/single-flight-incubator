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

use Hyperf\Incubator\Semaphore\Semaphore;
use Hyperf\Incubator\Semaphore\SemaphoreManager;

use function Hyperf\Coroutine\parallel;
use function Swoole\Coroutine\run;

run(static function (): void {
    echo 'Semaphore benchmarks', \PHP_EOL, \PHP_EOL;

    // ---- 1) uncontended fast path ----
    $sema = new Semaphore(1);
    $rounds = 1000000;
    $at = microtime(true);
    for ($i = 0; $i < $rounds; ++$i) {
        $sema->acquire(1);
        $sema->release(1);
    }
    printf("[1] 无竞争快路径 (acquire+release): %d ops/s\n", (int) ($rounds / (microtime(true) - $at)));

    // ---- 2) contended: 20 coroutines fighting for 4 tokens ----
    $sema = new Semaphore(4);
    $rounds = 100000;
    $callables = [];
    for ($i = 0; $i < 20; ++$i) {
        $callables[] = static function () use ($sema, $rounds): void {
            for ($j = 0; $j < $rounds / 20; ++$j) {
                $sema->acquire(1);
                $sema->release(1);
            }
        };
    }
    $at = microtime(true);
    parallel($callables);
    printf("[2] 高竞争路径 (20协程 x 4令牌, %d 次acquire): %d ops/s\n", $rounds, (int) ($rounds / (microtime(true) - $at)));

    // ---- 3) memory: manager lifecycle with unique keys, growth must stay flat ----
    $cycle = static function (int $rounds): void {
        for ($i = 0; $i < $rounds; ++$i) {
            $key = uniqid();
            $sema = SemaphoreManager::getSema($key, 1);
            $sema->acquire(1);
            $sema->release(1);
            SemaphoreManager::remove($key);
            unset($sema);
        }
    };
    $cycle(5000);
    $before = memory_get_usage();
    $cycle(5000);
    $middle = memory_get_usage();
    $cycle(40000);
    printf("[3] 内存: 唯一key完整生命周期 (预热5k后 5k→10k): %+d bytes, (10k→50k): %+d bytes\n", $middle - $before, memory_get_usage() - $middle);
});
