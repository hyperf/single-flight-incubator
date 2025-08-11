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

use Hyperf\Incubator\DoubleBarrier\DoubleBarrier;
use Swoole\Coroutine;

use function Swoole\Coroutine\defer;
use function Swoole\Coroutine\go;
use function Swoole\Coroutine\run;

run(static function () {
    $barrier = new DoubleBarrier(3);

    $queuedMs = 10;
    $startAt = (int) floor(microtime(true) * 1000);
    $biz = static function () use ($startAt, $queuedMs) {
        $cid = Coroutine::getCid();
        $elapsed = (int) floor(microtime(true) * 1000) - $startAt;
        printf("协程 [%d] 等待 %d 毫秒后组队成功，同时执行\n", $cid, $elapsed);
        if ($cid % 2) {
            usleep($queuedMs * 1000);
            printf("协程 [%d] 额外执行 %d 毫秒后，结束执行\n", $cid, $queuedMs);
        } else {
            printf("协程 [%d] 执行完毕，等待其他协程执行完毕，同时退出\n", $cid);
        }
    };

    $exit = static function () use ($startAt) {
        $cid = Coroutine::getCid();
        $elapsed = (int) floor(microtime(true) * 1000) - $startAt;
        printf("协程 [%d] 共执行 %d 毫秒，同时结束执行\n", $cid, $elapsed);
    };

    go(static function () use ($barrier, $biz, $exit) {
        defer($exit(...));
        $barrier->execute($biz(...));
    });
    go(static function () use ($barrier, $biz, $exit) {
        defer($exit(...));
        $barrier->execute($biz(...));
    });
    go(static function () use ($barrier, $biz, $queuedMs, $exit) {
        defer($exit(...));
        usleep($queuedMs * 1000);
        $barrier->execute($biz(...));
    });
});
