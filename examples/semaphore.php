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
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

use function Swoole\Coroutine\defer;
use function Swoole\Coroutine\go;
use function Swoole\Coroutine\run;

run(static function () {
    $sema = new Semaphore(3);

    go(static function () use ($sema) {
        $sleepSec = 1;
        $tokens = 1;
        defer(static function () use ($sema, $sleepSec, $tokens) {
            printf("协程 [%d] 占用信号量 %d 秒后释放\n", Coroutine::getCid(), $sleepSec);
            $sema->release($tokens);
        });

        $acquireAt = time();
        $sema->acquire($tokens);
        $resumedAt = time();
        $elapsed = $resumedAt - $acquireAt;
        printf("协程 [%d] 于 %d 秒后获取信号量成功\n", Coroutine::getCid(), $elapsed);
        sleep($sleepSec);
    });

    $chan = new Channel();
    go(static function () use ($sema, $chan) {
        $sleepSec = 2;
        $tokens = 2;
        defer(static function () use ($sema, $sleepSec, $tokens) {
            printf("协程 [%d] 占用信号量 %d 秒后释放\n", Coroutine::getCid(), $sleepSec);
            $sema->release($tokens);
        });

        $acquireAt = microtime(true);
        $sema->acquire($tokens);
        // 唤醒下面一个协程
        $chan->close();
        $resumedAt = microtime(true);
        $elapsed = ($resumedAt - $acquireAt) * 1000;
        printf("协程 [%d] 于 %d 秒后获取信号量成功\n", Coroutine::getCid(), $elapsed);
        sleep($sleepSec);
    });

    go(static function () use ($sema, $chan) {
        $tokens = 3;
        defer(static function () use ($sema, $tokens) {
            $sema->release($tokens);
            printf("协程 [%d] 释放信号量\n", Coroutine::getCid());
        });

        // 确保此协程在前一个协程后尝试获取信号量
        $chan->pop();
        $acquireAt = time();
        $sema->acquire($tokens);
        $resumedAt = time();
        $elapsed = $resumedAt - $acquireAt;
        printf("协程 [%d] 于 %d 秒后获取信号量成功\n", Coroutine::getCid(), $elapsed);
    });
});
