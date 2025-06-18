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

use Hyperf\Incubator\Barrier\CounterBarrier;
use Swoole\Coroutine;

use function Swoole\Coroutine\go;
use function Swoole\Coroutine\run;

run(static function () {
    $parties = 10;
    $barrier = new CounterBarrier($parties);
    $sleepMs = 5;

    for ($i = 0; $i < $parties - 1; ++$i) {
        go(static function () use ($barrier) {
            $waitAt = microtime(true);
            $barrier->await();
            // your biz logic here
            $resumeAt = microtime(true);
            $elapsed = ($resumeAt - $waitAt) * 1000;
            printf("协程 [%d] 等待 %.2f 毫秒后，恢复执行\n", Coroutine::getCid(), $elapsed);
        });
    }

    go(static function () use ($barrier, $sleepMs) {
        usleep($sleepMs * 1000);
        printf("协程 [%d] 作为最后一个协程，等待 %d 毫秒后加入屏障，同其他协程一起执行\n", Coroutine::getCid(), $sleepMs);
        $barrier->await();
        // your biz logic here
    });
});
