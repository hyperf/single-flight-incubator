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

namespace HyperfTest\Incubator\Cases\WorkerPool;

use Hyperf\Engine\Channel;
use Hyperf\Incubator\WorkerPool\Config;
use Hyperf\Incubator\WorkerPool\Exception\RuntimeException;
use Hyperf\Incubator\WorkerPool\Exception\TimeoutException;
use Hyperf\Incubator\WorkerPool\Task;
use Hyperf\Incubator\WorkerPool\WorkerPool;
use HyperfTest\Incubator\Stubs\WorkerPool\RetTask;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Throwable;

use function Hyperf\Coroutine\go;
use function Hyperf\Coroutine\parallel;

/**
 * @internal
 */
#[CoversNothing]
class WorkerPoolTest extends TestCase
{
    public function testSyncSubmit()
    {
        $config = new Config();
        $config->setCapacity(5);
        $pool = new WorkerPool($config);

        $result = $pool->submit(static fn () => 'test', sync: true);

        $this->assertEquals('test', $result);
        $pool->stop();
    }

    public function testSyncSubmitMultipleTasks()
    {
        $config = new Config();
        $config->setCapacity(5);
        $pool = new WorkerPool($config);

        $results = [];
        for ($i = 0; $i < 10; ++$i) {
            $results[] = $pool->submit(static fn () => $i, sync: true);
        }

        for ($i = 0; $i < 10; ++$i) {
            $this->assertEquals($i, $results[$i]);
        }

        $pool->stop();
    }

    public function testPoolCapacity()
    {
        $config = new Config();
        $config->setCapacity(2)->setMaxBlocks(0);
        $pool = new WorkerPool($config);

        $pool->submit(static fn () => usleep(100 * 1000));
        $pool->submit(static fn () => usleep(100 * 1000));

        try {
            $pool->submit(static fn () => 3);
            $this->fail('testPoolCapacity failed');
        } catch (Throwable $th) {
            $this->assertInstanceOf(RuntimeException::class, $th);
            $this->assertEquals('WorkerPool exhausted', $th->getMessage());
        }

        $pool->stop();
    }

    public function testTimeout()
    {
        $config = new Config();
        $config->setCapacity(1);
        $config->setMaxBlocks(1);
        $pool = new WorkerPool($config);

        $pool->submit(static fn () => usleep(200 * 1000));

        try {
            $pool->submit(static fn () => null, 0.1);
            $this->fail('testTimeout failed');
        } catch (Throwable $th) {
            $this->assertInstanceOf(TimeoutException::class, $th);
            $this->assertEquals('Waiting for available worker timeout', $th->getMessage());
        }

        $pool->stop();
    }

    public function testPreSpawn()
    {
        $config = new Config();
        $config->setCapacity(5);
        $config->setPreSpawn(true);
        $pool = new WorkerPool($config);

        $results = [];
        for ($i = 0; $i < 5; ++$i) {
            $results[] = $pool->submit(static fn () => $i, sync: true);
        }

        for ($i = 0; $i < 5; ++$i) {
            $this->assertEquals($i, $results[$i]);
        }

        $pool->stop();
    }

    public function testPoolTypes()
    {
        $config = new Config();
        $config->setPoolType(Config::QUEUE_POOL);
        $pool = new WorkerPool($config);

        $queue = null;
        $chan = new Channel();
        $ret = $pool->submit(static function () use (&$queue, $chan) {
            $queue = 'queue';
            $chan->push(1);
        });

        $this->assertNull($ret);
        $chan->pop();
        $this->assertEquals('queue', $queue);
        $pool->stop();

        $config = new Config();
        $config->setPoolType(Config::STACK_POOL);
        $pool = new WorkerPool($config);

        $stack = null;
        $chan = new Channel();
        $ret = $pool->submit(static function () use (&$stack, $chan) {
            $stack = 'stack';
            $chan->push(1);
        });

        $this->assertNull($ret);
        $chan->pop();
        $this->assertEquals('stack', $stack);
        $pool->stop();
    }

    public function testSubmitTask()
    {
        $config = new Config();
        $config->setCapacity(5);
        $pool = new WorkerPool($config);

        $task = new RetTask('ret', true);
        $result = $pool->submitTask($task);

        $this->assertEquals('ret', $result);
        $pool->stop();
    }

    public function testCollectWorkers()
    {
        $config = (new Config())->setCapacity(5)
            ->setPreSpawn(true)
            ->setCollectInactiveWorker(150);

        $pool = new WorkerPool($config);
        usleep(400 * 1000);

        $reflection = new ReflectionClass($pool);
        $workersProperty = $reflection->getProperty('workers');
        $workersProperty->setAccessible(true);
        $workers = $workersProperty->getValue($pool);

        $this->assertEquals(0, $workers->len());

        $pool->stop();
    }

    public function testMaxBlocks()
    {
        $config = new Config();
        $config->setCapacity(1)->setMaxBlocks(1);
        $pool = new WorkerPool($config);

        $sleepMs = mt_rand(100, 500);
        $pool->submit(static fn () => usleep($sleepMs * 1000));

        $startAt = (int) (microtime(true) * 1000);
        $task = new Task(static fn () => 'wait for worker');
        $pool->submitTask($task);
        $this->assertEquals('wait for worker', $task->waitResult());
        $endAt = (int) (microtime(true) * 1000);
        $waitForWorkerElapsed = (int) (($endAt - $startAt) * 1000);

        $this->assertGreaterThanOrEqual($sleepMs, $waitForWorkerElapsed);

        $pool->stop();
    }

    public function testMaxBlocksException()
    {
        $config = new Config();
        $config->setCapacity(1)->setMaxBlocks(1);
        $pool = new WorkerPool($config);

        $pool->submit(static fn () => usleep(100 * 1000));

        go(static fn () => $pool->submit(static fn () => usleep(100 * 1000)));

        go(function () use ($pool) {
            try {
                $pool->submit(static fn () => "won't get a worker");
                $this->fail('max blocks exception test failed');
            } catch (Throwable $th) {
                $this->assertInstanceOf(RuntimeException::class, $th);
                $this->assertEquals('WorkerPool exhausted', $th->getMessage());
            }
        });

        $pool->stop();
    }

    public function testTimeoutException()
    {
        $config = new Config();
        $config->setCapacity(1)->setMaxBlocks(1);
        $pool = new WorkerPool($config);

        $pool->submit(static fn () => usleep(100 * 1000));

        try {
            $pool->submit(static fn () => 'time out', 0.05);
            $this->fail('timeout exception test failed');
        } catch (Throwable $th) {
            $this->assertInstanceOf(TimeoutException::class, $th);
            $this->assertEquals('Waiting for available worker timeout', $th->getMessage());
        }

        $pool->stop();
    }

    public function testConcurrencyLimit()
    {
        $capacity = 50;
        $total = 1000;
        $config = new Config();
        $config->setCapacity($capacity)->setMaxBlocks($total - $capacity);
        $pool = new WorkerPool($config);

        $sleepMs = mt_rand(5, 10);
        $startAt = microtime(true);
        for ($i = 0; $i < $total; ++$i) {
            $pool->submit(static fn () => usleep($sleepMs * 1000));
        }
        $endAt = microtime(true);

        $this->assertGreaterThanOrEqual($total / $capacity * $sleepMs * 0.9, (int) (($endAt - $startAt) * 1000));

        $pool->stop();
    }

    public function testConcurrencyLimitWithTimeout()
    {
        $capacity = 50;
        $total = 1000;
        $config = new Config();
        $extraNum = mt_rand(10, $total - $capacity);
        $config->setCapacity($capacity)->setMaxBlocks($total - $capacity - $extraNum);
        $pool = new WorkerPool($config);

        $sleepMs = mt_rand(5, 10);
        $exceptionNum = 0;
        $tasks = [];
        for ($i = 0; $i < $total; ++$i) {
            $tasks[] = static function () use ($pool, $sleepMs, &$exceptionNum) {
                try {
                    $pool->submit(static fn () => usleep($sleepMs * 1000));
                } catch (RuntimeException $e) {
                    ++$exceptionNum;
                }
            };
        }
        parallel($tasks);

        $this->assertEquals($extraNum, $exceptionNum);

        $pool->stop();
    }
}
