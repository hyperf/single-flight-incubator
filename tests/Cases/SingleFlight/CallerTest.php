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
use Hyperf\Incubator\SingleFlight\SingleFlight;
use HyperfTest\Incubator\Stubs\SingleFlight\FooException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
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

    public function testChannelIsNotCreatedOnConstruction()
    {
        $caller = new Caller(uniqid());

        $rp = new ReflectionProperty(Caller::class, 'channel');
        $this->assertNull($rp->getValue($caller));
    }

    public function testLeaderDoesNotCreateChannelWithoutWaiters()
    {
        $key = uniqid();
        $rp = new ReflectionProperty(Caller::class, 'channel');

        SingleFlight::do($key, function () use ($key, $rp) {
            // while the processor is running, the caller of this generation is
            // registered in the container: inspect it in place
            $caller = SingleFlight::get($key);
            $this->assertInstanceOf(Caller::class, $caller);
            $this->assertNull($rp->getValue($caller), 'channel must not be allocated when no waiter has blocked');

            return 'foo';
        });
    }

    public function testChannelIsCreatedLazilyWhenWaiterBlocks()
    {
        $caller = new Caller($key = uniqid());
        $rp = new ReflectionProperty(Caller::class, 'channel');

        go(function () use ($caller, $rp) {
            $caller->share(function () use ($caller, $rp): string {
                // go() runs this coroutine immediately, so yield here to let
                // the main coroutine reach wait() and block on the channel
                for ($i = 0; $i < 100 && $caller->waiters() === 0; ++$i) {
                    usleep(100);
                }
                // the main coroutine is now blocked as a waiter: the channel
                // must have been created by it, and it must be the only waiter
                $this->assertEquals(1, $caller->waiters());
                $this->assertNotNull($rp->getValue($caller));

                return 'foo';
            });
        });

        // the main coroutine finds done === false, creates the channel
        // lazily and blocks on it until the leader shares the result
        $this->assertSame('foo', $caller->wait());
    }
}
