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

use Hyperf\Incubator\SingleFlight\SingleFlight;

use function Hyperf\Coroutine\parallel;
use function Swoole\Coroutine\run;

run(static function (): void {
    echo 'SingleFlight benchmarks', \PHP_EOL, \PHP_EOL;

    // ---- 1) merge throughput: concurrent do() on one key, single execution ----
    $key = uniqid();
    $rounds = 10000;
    $callables = [];
    for ($i = 0; $i < $rounds; ++$i) {
        $callables[] = static fn (): mixed => SingleFlight::do($key, static fn (): int => usleep(1000) ? 0 : 1);
    }
    $at = microtime(true);
    parallel($callables);
    printf("[1] 合并吞吐 (%d 并发 do 同一key, processor 执行1次): %d req/s\n", $rounds, (int) ($rounds / (microtime(true) - $at)));

    // ---- 2) leader path churn: sequential do() with unique keys ----
    $rounds = 20000;
    $at = microtime(true);
    for ($i = 0; $i < $rounds; ++$i) {
        SingleFlight::do(uniqid(), static fn (): int => 1);
    }
    printf("[2] leader 建销吞吐 (%d 个唯一key串行 do): %d req/s\n", $rounds, (int) ($rounds / (microtime(true) - $at)));

    // ---- 3) memory: unique key lifecycles ----
    $before = memory_get_usage();
    for ($i = 0; $i < 5000; ++$i) {
        SingleFlight::do(uniqid(), static fn (): int => 1);
    }
    printf("[3] 内存: 5000个唯一key的 do 生命周期: 增量 %d bytes\n", memory_get_usage() - $before);
});
