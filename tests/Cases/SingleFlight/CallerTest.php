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

namespace HyperfTest\Incubator\Cases\SingleFlight;

use Hyperf\Incubator\SingleFlight\Caller;
use Hyperf\Incubator\SingleFlight\Exception\ForgetException;
use Hyperf\Incubator\SingleFlight\Exception\RuntimeException;
use Hyperf\Incubator\SingleFlight\Exception\TimeoutException;
use HyperfTest\Incubator\Stubs\SingleFlight\FooException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Throwable;

use function Hyperf\Coroutine\defer;
use function Hyperf\Coroutine\go;
use function Hyperf\Coroutine\parallel;

/**
 * @internal
 */
#[CoversNothing]
class CallerTest extends TestCase
{
    public function testShare()
    {
        $ret = 'foo';
        $share = 'share_';
        $caller = new Caller(uniqid());
        $shared = $caller->share((static fn () => $share . $ret)(...));

        $this->assertSame($share . $ret, $shared);
    }

    public function testShareWithException()
    {
        $key = uniqid();
        $exMsg = 'foo message';
        $fooBiz = static fn () => throw new FooException($exMsg);

        $caller = new Caller($key);

        try {
            $caller->share($fooBiz);
            $this->fail('testShareWithRuntimeException failed: no exception occurred');
        } catch (Throwable $th) {
            $this->assertInstanceOf(RuntimeException::class, $th);
            $this->assertInstanceOf(FooException::class, $th->getPrevious());
            $this->assertSame("An exception occurred while sharing the result on {$key}", $th->getMessage());
            $this->assertSame($exMsg, $th->getPrevious()->getMessage());
        }
    }

    public function testWait()
    {
        $key = uniqid();
        $caller = new Caller($key);
        $expected = 'foo';

        go(static fn () => $caller->share(static fn () => $expected));

        $result = $caller->wait();
        $this->assertSame($expected, $result);
    }

    public function testWaitWithRuntimeException()
    {
        $key = uniqid();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("An exception occurred while sharing the result on {$key}");

        $caller = new Caller($key);

        go(static function () use ($caller) {
            try {
                $caller->share(static fn () => throw new FooException(''));
                $this->fail('testWaitWithException failed: no exception occurred');
            } catch (Throwable) {
            }
        });

        $caller->wait();
        $this->fail('testWaitWithRuntimeException failed: no exception occurred');
    }

    public function testWaitWithTimeoutException()
    {
        $key = uniqid();

        $this->expectException(TimeoutException::class);
        $this->expectExceptionMessage("Exceeded maximum waiting time for result on {$key}");

        $caller = new Caller($key);
        $sleepMs = 5;
        $waitMs = 2;

        go(static fn () => usleep($sleepMs * 1000));

        $caller->wait($waitMs / 1000);
        $this->fail('testWaitWithTimeoutException failed: no exception occurred');
    }

    public function testWaitAfterDone()
    {
        $key = uniqid();
        $caller = new Caller($key);
        $expected = 'foo';

        $caller->share(fn () => $expected);

        $result = $caller->wait();
        $this->assertSame($expected, $result);
    }

    public function testWaitAfterException()
    {
        $key = uniqid();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("An exception occurred while sharing the result on {$key}");

        $caller = new Caller($key);
        $exMsg = 'foo message';

        try {
            $caller->share(static fn () => throw new FooException($exMsg));
            $this->fail('testWaitAfterException failed: no exception occurred');
        } catch (Throwable) {
        }

        $caller->wait();
        $this->fail('testWaitAfterException failed: no exception occurred');
    }

    public function testForget()
    {
        $key = uniqid();

        $this->expectException(ForgetException::class);
        $this->expectExceptionMessage("SingleFlight {$key} has been forgotten while waiting for the result");

        $caller = new Caller($key);

        go(function () use ($caller) {
            usleep(1000);
            $caller->forget();
            $this->assertTrue($caller->isForgotten());
        });

        $caller->wait();
        $this->fail('testForget failed: no exception occurred');
    }

    public function testWaiters()
    {
        $key = uniqid();
        $caller = new Caller($key);
        $waiterCount = 5;

        $callables = [];
        for ($i = $waiterCount; $i > 0; --$i) {
            $callables[] = function () use ($caller, $i) {
                defer(fn () => $this->assertEquals($i - 1, $caller->waiters()));
                return $caller->wait();
            };
        }

        go(function () use ($caller, $waiterCount) {
            $caller->share(function () use ($caller, $waiterCount) {
                usleep(10 * 1000);
                $this->assertEquals($waiterCount, $caller->waiters());
                return 'foo';
            });
        });

        $results = parallel($callables);

        $this->assertCount(1, array_unique($results));
        $this->assertEquals(0, $caller->waiters());
    }
}
