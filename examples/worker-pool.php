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
use Hyperf\Incubator\WorkerPool\Task;
use Hyperf\Incubator\WorkerPool\WorkerPool;
use Swoole\Coroutine;

use function Swoole\Coroutine\defer;
use function Swoole\Coroutine\run;

run(static function () {
    $config = new Config();
    $config->setCapacity(5);
    $pool = new WorkerPool($config);
    defer(static fn () => $pool->stop());

    // 投递异步任务
    $mockBiz = static fn () => Coroutine::getCid();
    $pool->submit($mockBiz);

    // 投递同步任务，可直接获取结果
    $ret = $pool->submit($mockBiz, sync: true);
    if (Coroutine::getCid() != $ret) {
        printf("同步任务投递到worker-pool中的工作协程执行\n");
    }

    // 投递异步任务，通过waitResult方法获取结果
    $task = new Task($mockBiz(...), sync: false);
    $nullRet = $pool->submitTask($task);
    if (is_null($nullRet)) {
        printf("投递异步任务不会直接得到返回值\n");
    }
    $ret = $task->waitResult();
    if (Coroutine::getCid() != $ret) {
        printf("异步任务投递到worker-pool中的工作协程执行\n");
    }
});
