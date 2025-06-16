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
        $this->assertEquals(0, $waiters->len());

        $semaphore->acquire(1);
        $waiters = $waitersProperty->getValue($semaphore);
        $this->assertEquals(0, $waiters->len());

        $semaphore->release(1);
        $waiters = $waitersProperty->getValue($semaphore);
        $this->assertEquals(0, $waiters->len());

        $semaphore->acquire(1);
        $semaphore->acquire(1);
        $waiters = $waitersProperty->getValue($semaphore);
        $this->assertEquals(0, $waiters->len());
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
            $this->assertEquals(1, $waiters->len());
            $semaphore->release(1);
        });
        $chan->pop();

        $this->assertEquals(0, $waiters->len());
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
            $this->assertEquals(2, $waiters->len());
            $semaphore->release(1);
            $this->assertEquals(1, $waiters->len());
            usleep(100 * 1000);
            $semaphore->release(1);
        });
        $chan->pop();

        $this->assertEquals(0, $waiters->len());
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
            $this->assertEquals(2, $waiters->len());
            $semaphore->release(3);
            $this->assertEquals(1, $waiters->len());
            usleep(100 * 1000);
            $semaphore->release(3);
        });
        $chan->pop();

        $this->assertEquals(0, $waiters->len());
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
            $this->assertEquals(1, $waiters->len());
            $semaphore->release(1);
        });
        $chan->pop();

        $this->assertEquals(0, $waiters->len());
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
}
