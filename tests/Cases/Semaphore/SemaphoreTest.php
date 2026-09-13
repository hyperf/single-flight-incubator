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

namespace HyperfTest\Incubator\Cases\Semaphore;

use Hyperf\Engine\Channel;
use Hyperf\Incubator\Semaphore\Exception\RuntimeException;
use Hyperf\Incubator\Semaphore\Exception\SemaphoreException;
use Hyperf\Incubator\Semaphore\Exception\TimeoutException;
use Hyperf\Incubator\Semaphore\Semaphore;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function Hyperf\Coroutine\go;
use function Hyperf\Coroutine\parallel;

/**
 * @internal
 */
#[CoversNothing]
class SemaphoreTest extends TestCase
{
    public function testSemaphoreWithInvalidTokens()
    {
        $this->expectExceptionMessage('The number of tokens must be greater than or equal to 1');
        $sema = new Semaphore(1);
        $sema->acquire(-1);
    }

    public function testSemaphoreWithTooManyTokens()
    {
        $this->expectExceptionMessage('The number of tokens requested exceeds the semaphore size');
        $sema = new Semaphore(2);
        $sema->acquire(3);
    }

    public function testSemaphoreWithNoWaiters()
    {
        $semaphore = new Semaphore(2);

        $reflection = new ReflectionClass($semaphore);
        $waitersProperty = $reflection->getProperty('waiters');
        $waitersProperty->setAccessible(true);

        $waiters = $waitersProperty->getValue($semaphore);
        $this->assertEquals(0, count($waiters));

        $semaphore->acquire(1);
        $waiters = $waitersProperty->getValue($semaphore);
        $this->assertEquals(0, count($waiters));

        $semaphore->release(1);
        $waiters = $waitersProperty->getValue($semaphore);
        $this->assertEquals(0, count($waiters));

        $semaphore->acquire(1);
        $semaphore->acquire(1);
        $waiters = $waitersProperty->getValue($semaphore);
        $this->assertEquals(0, count($waiters));
    }

    public function testSemaphoreWithWaiter()
    {
        $semaphore = new Semaphore(1);

        $reflection = new ReflectionClass($semaphore);
        $waitersProperty = $reflection->getProperty('waiters');
        $waitersProperty->setAccessible(true);
        $waiters = $waitersProperty->getValue($semaphore);

        $semaphore->acquire(1);

        $chan = new Channel();
        go(function () use ($semaphore, $chan) {
            $semaphore->acquire(1);
            $chan->close();
        });
        go(function () use ($semaphore, $waiters) {
            usleep(100 * 1000);
            $this->assertEquals(1, count($waiters));
            $semaphore->release(1);
        });
        $chan->pop();

        $this->assertEquals(0, count($waiters));
    }

    public function testSemaphoreWithWaiters()
    {
        $semaphore = new Semaphore(1);

        $reflection = new ReflectionClass($semaphore);
        $waitersProperty = $reflection->getProperty('waiters');
        $waitersProperty->setAccessible(true);
        $waiters = $waitersProperty->getValue($semaphore);

        $semaphore->acquire(1);

        go(function () use ($semaphore) {
            $semaphore->acquire(1);
        });

        $chan = new Channel();
        go(function () use ($semaphore, $chan) {
            $semaphore->acquire(1);
            $chan->close();
        });
        go(function () use ($semaphore, $waiters) {
            usleep(100 * 1000);
            $this->assertEquals(2, count($waiters));
            $semaphore->release(1);
            $this->assertEquals(1, count($waiters));
            usleep(100 * 1000);
            $semaphore->release(1);
        });
        $chan->pop();

        $this->assertEquals(0, count($waiters));
    }

    public function testSemaphoreWithWaitersWithAcquireMoreThanOneToken()
    {
        $semaphore = new Semaphore(3);

        $reflection = new ReflectionClass($semaphore);
        $waitersProperty = $reflection->getProperty('waiters');
        $waitersProperty->setAccessible(true);
        $waiters = $waitersProperty->getValue($semaphore);

        $semaphore->acquire(3);

        go(function () use ($semaphore) {
            $semaphore->acquire(3);
        });

        $chan = new Channel();
        go(function () use ($semaphore, $chan) {
            $semaphore->acquire(3);
            $chan->close();
        });
        go(function () use ($semaphore, $waiters) {
            usleep(100 * 1000);
            $this->assertEquals(2, count($waiters));
            $semaphore->release(3);
            $this->assertEquals(1, count($waiters));
            usleep(100 * 1000);
            $semaphore->release(3);
        });
        $chan->pop();

        $this->assertEquals(0, count($waiters));
    }

    public function testAcquireWithTimeout()
    {
        $this->expectException(TimeoutException::class);

        $semaphore = new Semaphore(1);
        $semaphore->acquire(1);

        $semaphore->acquire(1, 0.001);
    }

    public function testSemaphoreWithTimeoutWithWaiters()
    {
        $semaphore = new Semaphore(1);

        $reflection = new ReflectionClass($semaphore);
        $waitersProperty = $reflection->getProperty('waiters');
        $waitersProperty->setAccessible(true);
        $waiters = $waitersProperty->getValue($semaphore);

        $semaphore->acquire(1);
        $chan = new Channel();
        go(function () use ($semaphore, $chan) {
            try {
                $semaphore->acquire(1, 0.05);
            } catch (SemaphoreException $exception) {
                $this->assertInstanceOf(TimeoutException::class, $exception);
            }
            $chan->close();
        });
        go(function () use ($semaphore, $waiters) {
            usleep(100 * 1000);
            $this->assertEquals(1, count($waiters));
            $semaphore->release(1);
        });
        $chan->pop();

        $this->assertEquals(0, count($waiters));
    }

    public function testTryAcquire()
    {
        $semaphore = new Semaphore(2);

        $ret = $semaphore->tryAcquire(1);
        $this->assertTrue($ret);

        $ret = $semaphore->tryAcquire(1);
        $this->assertTrue($ret);

        $ret = $semaphore->tryAcquire(1);
        $this->assertFalse($ret);

        $semaphore->release(1);

        $ret = $semaphore->tryAcquire(1);
        $this->assertTrue($ret);
    }

    public function testConcurrentAcquireAndRelease()
    {
        $num = 10000;
        $sema = new Semaphore(2);
        $acquireTimes = 0;
        $releaseTimes = 0;

        $callables = [];
        for ($j = 0; $j < $num; ++$j) {
            $callables[] = static function () use ($sema, &$acquireTimes, &$releaseTimes) {
                usleep(1);
                $sema->acquire(1);
                ++$acquireTimes;
                usleep(1);
                $sema->release(1);
                ++$releaseTimes;
            };
        }
        parallel($callables);

        $this->assertEquals($num, $acquireTimes);
        $this->assertEquals($num, $releaseTimes);
    }

    public function testWaitersAreGrantedInFIFOOrder()
    {
        $semaphore = new Semaphore(1);
        $semaphore->acquire(1);

        $granted = new Channel(16);
        for ($i = 0; $i < 5; ++$i) {
            go(function () use ($semaphore, $granted, $i) {
                $semaphore->acquire(1);
                $granted->push($i);
                $semaphore->release(1);
            });
        }

        // ensure that all coroutines are queued in order
        usleep(100 * 1000);
        $semaphore->release(1);

        $order = [];
        for ($i = 0; $i < 5; ++$i) {
            $order[] = $granted->pop(1);
        }

        $this->assertSame(range(0, 4), $order);
    }

    public function testTimedOutWaiterPassesGrantToSuccessors()
    {
        $semaphore = new Semaphore(5);
        $semaphore->acquire(4);

        $results = new Channel(8);
        go(function () use ($semaphore, $results) {
            try {
                $semaphore->acquire(3, 0.05);
            } catch (TimeoutException) {
                $results->push('head-timeout');
            }
        });
        go(function () use ($semaphore, $results) {
            $semaphore->acquire(1);
            $results->push('tail-granted');
            $semaphore->release(1);
        });

        usleep(200 * 1000);

        $ret = [$results->pop(1), $results->pop(1)];
        sort($ret);
        $this->assertSame(['head-timeout', 'tail-granted'], $ret);
    }

    public function testReleaseMoreThanHeldKeepsStateIntact()
    {
        $semaphore = new Semaphore(2);
        $semaphore->acquire(2);

        try {
            $semaphore->release(3);
            $this->fail('RuntimeException expected');
        } catch (RuntimeException $exception) {
            $this->assertEquals('Semaphore released more than held', $exception->getMessage());
        }

        $semaphore->release(2);
        $this->assertTrue($semaphore->tryAcquire(2));
    }
}
