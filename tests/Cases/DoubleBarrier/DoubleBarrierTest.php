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

namespace HyperfTest\Incubator\Cases\DoubleBarrier;

use Exception;
use Hyperf\Coroutine\Exception\ParallelExecutionException;
use Hyperf\Engine\Channel;
use Hyperf\Incubator\DoubleBarrier\DoubleBarrier;
use Hyperf\Incubator\DoubleBarrier\Exception\EnterException;
use Hyperf\Incubator\DoubleBarrier\Exception\LeaveException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Throwable;

use function Hyperf\Coroutine\go;
use function Hyperf\Coroutine\parallel;

/**
 * @internal
 */
#[CoversNothing]
class DoubleBarrierTest extends TestCase
{
    public function testEnterAndLeave()
    {
        $parties = mt_rand(10, 100);
        $barrier = new DoubleBarrier($parties);
        $callbacks = [];
        $expected = [];

        for ($i = 0; $i < $parties; ++$i) {
            $expected[] = $i;
            $callbacks[] = function () use ($barrier, $i) {
                $barrier->enter();
                usleep(mt_rand(1000, 10000));
                $barrier->leave();
                return $i;
            };
        }
        $results = parallel($callbacks);

        sort($results);
        $this->assertSame($expected, $results);
    }

    public function testExtraEnters()
    {
        $this->expectException(EnterException::class);
        $this->expectExceptionMessage('Cannot enter barrier while in leaving state');
        $barrier = new DoubleBarrier(1);
        try {
            $barrier->enter();
            $barrier->enter();
        } finally {
            $barrier->leave();
        }
    }

    public function testEnterTimeout()
    {
        $this->expectException(EnterException::class);
        $this->expectExceptionMessage('Timeout while waiting others to enter barrier');

        $barrier = new DoubleBarrier(2);
        $barrier->enter(0.001);
    }

    public function testLeaveTimeoutException()
    {
        $this->expectException(ParallelExecutionException::class);
        $this->expectExceptionMessageMatches('/^.*Timeout while waiting others to leave barrier.*$/s');

        $barrier = new DoubleBarrier(2);
        parallel([
            static function () use ($barrier) {
                $barrier->enter();
                $barrier->leave(0.001);
            },
            static fn () => $barrier->enter(),
        ]);
    }

    public function testCannotLeaveBeforeEntering()
    {
        $barrier = new DoubleBarrier(2);
        try {
            $barrier->leave();
            $this->fail('Expected LeaveException was not thrown.');
        } catch (LeaveException $e) {
            $this->assertSame('Cannot leave barrier before fully entering', $e->getMessage());
        }
    }

    public function testExtraLeave()
    {
        $this->expectException(LeaveException::class);
        $this->expectExceptionMessage('Cannot leave barrier that is already done');

        $barrier = new DoubleBarrier(1);
        $barrier->enter();
        $barrier->leave();
        $barrier->leave();
    }

    public function testExecuteMethodNormalCase()
    {
        $ret = uniqid();
        $barrier = new DoubleBarrier(1);
        $result = $barrier->execute(fn () => $ret);

        $this->assertSame($ret, $result);
    }

    public function testExecuteMethodWithExceptionInCallback()
    {
        $barrier = new DoubleBarrier(1);
        $exception = new Exception(uniqid());

        try {
            $barrier->execute(static fn () => throw $exception);
        } catch (Throwable $e) {
            $this->assertSame($exception, $e->getPrevious());
        }
    }

    public function testExecuteWithEnterTimeout()
    {
        $this->expectException(ParallelExecutionException::class);
        $this->expectExceptionMessageMatches('/Timeout while waiting others to enter barrier/s');

        $num = mt_rand(3, 10);
        $barrier = new DoubleBarrier($num);
        $executes = array_fill(0, $num - 1, fn () => $barrier->execute(fn () => $this->fail('Callback should not run.'), 0.001));
        $executes[] = static fn () => null; // This coroutine does nothing, causing the others to time out
        parallel($executes);
    }

    public function testExecuteWithLeaveTimeout()
    {
        $this->expectException(ParallelExecutionException::class);
        $this->expectExceptionMessageMatches('/Timeout while waiting others to leave barrier/s');

        $num = mt_rand(3, 10);
        $barrier = new DoubleBarrier($num);
        $executes = array_fill(0, $num - 1, static fn () => $barrier->execute(static fn () => null, -1, 0.001));
        $executes[] = static fn () => $barrier->enter();
        parallel($executes);
    }

    public function testAutoLeaveWorksWithCallbackExceptions()
    {
        $num = mt_rand(10, 100);
        $barrier = new DoubleBarrier($num);

        $executes = [];
        for ($i = 0; $i < $num - 1; ++$i) {
            $executes[] = static fn () => $barrier->execute(static fn () => throw new Exception());
        }
        $executes[] = static fn () => $barrier->execute(static fn () => null);
        try {
            parallel($executes);
            $this->fail('A ParallelExecutionException should have been thrown.');
        } catch (ParallelExecutionException $e) {
            $this->assertCount($num - 1, $e->getThrowables());
        }
    }

    public function testEnterTimeoutBreaksBarrierForWaiters()
    {
        $barrier = new DoubleBarrier(4);
        $chan = new Channel(4);

        // two waiters with long timeouts, they must be woken by the break, not by their own timeouts
        go(static function () use ($barrier, $chan): void {
            try {
                $barrier->enter(5.0);
                $chan->push('A:entered');
            } catch (EnterException $e) {
                $chan->push('A:' . $e->getMessage());
            }
        });
        go(static function () use ($barrier, $chan): void {
            try {
                $barrier->enter(5.0);
                $chan->push('B:entered');
            } catch (EnterException $e) {
                $chan->push('B:' . $e->getMessage());
            }
        });

        usleep(50 * 1000);
        $at = microtime(true);
        go(static function () use ($barrier, $chan): void {
            try {
                $barrier->enter(0.05);
            } catch (EnterException $e) {
                $chan->push('C:' . $e->getMessage());
            }
        });

        $ret = [$chan->pop(1.0), $chan->pop(1.0), $chan->pop(1.0)];
        $elapsed = microtime(true) - $at;
        sort($ret);

        // the waiters fail fast with the break, far before their own 5s timeouts
        $this->assertLessThan(1.0, $elapsed);
        $this->assertSame(['A:The barrier was broken by another party', 'B:The barrier was broken by another party', 'C:Timeout while waiting others to enter barrier'], $ret);
    }

    public function testPartyFailureDoesNotDetonateDestructor()
    {
        $barrier = new DoubleBarrier(2);

        try {
            $barrier->enter(0.001);
        } catch (EnterException) {
        }

        // a failed flow must not throw from the destructor when the object is destroyed
        unset($barrier);
        $this->assertTrue(true);
    }

    public function testPartyFailureDoesNotPoisonOtherPartiesLeave()
    {
        $barrier = new DoubleBarrier(2);
        $timeChan = new Channel(1);
        $resultChan = new Channel(2);

        // A's callback is instant, it must stay waiting for B's slow callback on leave
        go(static function () use ($barrier, $timeChan): void {
            $at = microtime(true);
            $barrier->execute(static fn (): null => null);
            $timeChan->push(microtime(true) - $at);
        });
        go(static function () use ($barrier): void {
            $barrier->execute(static fn (): null => usleep(200 * 1000));
        });

        // C fails to enter while the others are running, its failure must not skip their leave
        usleep(100 * 1000);
        go(static function () use ($barrier, $resultChan): void {
            try {
                $barrier->execute(static fn (): null => null);
            } catch (EnterException $e) {
                $resultChan->push('C:' . $e->getMessage());
            }
        });

        // A waited for B's whole 200ms callback on leave, instead of being skipped
        $this->assertGreaterThan(0.15, $timeChan->pop(3.0));
        $this->assertSame('C:Cannot enter barrier while in leaving state', $resultChan->pop(3.0));
    }
}
