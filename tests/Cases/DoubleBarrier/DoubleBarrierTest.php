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
use Hyperf\Incubator\DoubleBarrier\DoubleBarrier;
use Hyperf\Incubator\DoubleBarrier\Exception\EnterException;
use Hyperf\Incubator\DoubleBarrier\Exception\LeaveException;
use Hyperf\Incubator\DoubleBarrier\Exception\RuntimeException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Throwable;

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

    public function testWithoutLeaveException()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DoubleBarrier was not properly leaved before destruction');
        new DoubleBarrier(mt_rand(1, 100));
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
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DoubleBarrier was not properly leaved before destruction');
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
}
