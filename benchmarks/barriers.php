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

use Hyperf\Incubator\Barrier\BarrierManager;
use Hyperf\Incubator\DoubleBarrier\DoubleBarrier;

use function Hyperf\Coroutine\parallel;
use function Swoole\Coroutine\run;

run(static function (): void {
    echo 'Barrier benchmarks', \PHP_EOL, \PHP_EOL;

    // ---- 1) CounterBarrier manager batch trips ----
    $waves = 500;
    $parties = 10;
    $callables = [];
    for ($w = 0; $w < $waves; ++$w) {
        $key = 'wave_' . $w;
        for ($i = 0; $i < $parties; ++$i) {
            $callables[] = static fn (): mixed => BarrierManager::counterCall($key, $parties, static fn (): null => null);
        }
    }
    $at = microtime(true);
    parallel($callables);
    printf("[1] CounterBarrier 汇合吞吐 (%d 波 x %d 方): %d parties/s\n", $waves, $parties, (int) ($waves * $parties / (microtime(true) - $at)));

    // ---- 2) DoubleBarrier grouped enter+execute+leave ----
    $groups = 300;
    $parties = 3;
    $callables = [];
    for ($g = 0; $g < $groups; ++$g) {
        $barrier = new DoubleBarrier($parties);
        for ($i = 0; $i < $parties; ++$i) {
            $callables[] = static fn (): mixed => $barrier->execute(static fn (): null => null);
        }
    }
    $at = microtime(true);
    parallel($callables);
    printf("[2] DoubleBarrier 组队吞吐 (%d 组 x %d 方): %d parties/s\n", $groups, $parties, (int) ($groups * $parties / (microtime(true) - $at)));

    // ---- 3) memory: manager barrier batch lifecycles ----
    unset($callables);
    $before = memory_get_usage();
    $callables = [];
    for ($i = 0; $i < 2500; ++$i) {
        $key = uniqid();
        $callables[] = static fn (): mixed => BarrierManager::counterCall($key, 2, static fn (): null => null);
        $callables[] = static fn (): mixed => BarrierManager::counterCall($key, 2, static fn (): null => null);
    }
    parallel($callables);
    unset($callables);
    printf("[3] 内存: 2500个屏障批次生命周期: 增量 %d bytes\n", memory_get_usage() - $before);
});
