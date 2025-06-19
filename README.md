# Hyperf Concurrent Tools

一个hyperf常用并发工具库，包括single-flight、barrier、semaphore、worker-pool

## 安装

```bash
composer require hyperf/single-flight-incubtor
```

## 基本使用
**所有例子都在[examples](examples)目录下，更多用法请参考[tests](tests)目录**
### single-flight
```php
$ret = [];
$barrierKey = uniqid();

run(static function () use (&$ret, $barrierKey) {
    for ($i = 0; $i < 10; ++$i) {
        go(static function () use (&$ret, $barrierKey, $i) {
            $ret[] = SingleFlight::do($barrierKey, static function () use ($i) {
                // ensure that other coroutines can be scheduled at the same time
                usleep(1000);
                return [Coroutine::getCid() => $i];
            });
        });
    }
});

if (count(array_unique($ret)) === 1) {
    $ret = var_export($ret, true);
    printf("%s\n只有一个协程会执行闭包逻辑，其他协程等待其结果进行复用\n", $ret);
}
```

### barrier
```php
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
```

### semaphore
```php
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
```

### worker-pool
```php
run(static function () {
    $config = new Config();
    $config->setCapacity(5);
    $pool = new WorkerPool($config);

    // 关闭协程池
    defer(static fn () => $pool->stop());

    // 投递异步任务
    $mockBiz = static fn (): int => Coroutine::getCid();
    $ret = $pool->submit($mockBiz);
    if (is_null($ret)) {
        printf("投递异步任务若不关心返回值可直接忽略\n");
    }

    // 投递同步任务，可直接获取结果
    $ret = $pool->submit($mockBiz, sync: true);
    if (Coroutine::getCid() !== $ret) {
        printf("同步任务投递到worker-pool中的工作协程执行\n");
    }

    // 投递异步任务，通过waitResult方法获取结果
    $task = new Task($mockBiz(...), sync: false);
    $nullRet = $pool->submitTask($task);
    if (is_null($nullRet)) {
        printf("投递异步任务不会直接得到返回值，可通过waitResult方法获取\n");
    }
    $ret = $task->waitResult();
    if (Coroutine::getCid() !== $ret) {
        printf("异步任务投递到worker-pool中的工作协程执行\n");
    }
});
```