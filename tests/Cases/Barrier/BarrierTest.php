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

namespace HyperfTest\Barrier;

use Exception;
use Hyperf\Incubator\Barrier\BarrierManager;
use Hyperf\Incubator\Barrier\CounterBarrier;
use Hyperf\Incubator\Barrier\Exception\RuntimeException;
use Hyperf\Incubator\Barrier\Exception\TimeoutException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

use function Hyperf\Coroutine\parallel;

/**
 * @internal
 */
#[CoversNothing]
class BarrierTest extends TestCase
{
    public function testCounterBarrier()
    {
        $parties = 10000;
        $barrier = new CounterBarrier($parties);

        $this->assertFalse($barrier->broken());
        $this->assertEquals(0, $barrier->waiters());

        $callables = [];
        $waitMs = mt_rand(100, 1000);

        for ($i = 0; $i < $parties - 1; ++$i) {
            $callables[] = static function () use ($barrier) {
                $startAt = microtime(true);
                $barrier->await();
                $resumeAt = microtime(true);
                return $resumeAt - $startAt;
            };
        }

        $callables[] = static function () use ($barrier, $waitMs) {
            usleep($waitMs * 1000);
            $barrier->await();
        };

        $retAt = array_filter(parallel($callables));
        $this->assertCount($parties - 1, $retAt);
        $minAt = min($retAt);

        $this->assertGreaterThanOrEqual($waitMs, (int) ($minAt * 1000));

        $this->assertTrue($barrier->broken());
        $this->assertEquals(0, $barrier->waiters());
    }

    public function testCounterBarrierTimeoutException()
    {
        $parties = 2;
        $barrier = new CounterBarrier($parties);

        parallel([
            function () use ($barrier) {
                try {
                    $barrier->await(0.1);
                } catch (Exception $exception) {
                    $this->assertInstanceOf(TimeoutException::class, $exception);
                }
            },
            function () use ($barrier) {
                usleep(150 * 1000);
                $barrier->await();
            },
            function () use ($barrier) {
                usleep(200 * 1000);
                $barrier->await();
            },
        ]);

        $this->assertTrue($barrier->broken());
        $this->assertEquals(0, $barrier->waiters());
    }

    public function testBarrierManagerInvalidParties()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Parties must be greater than 1');
        BarrierManager::awaitForCounter(uniqid(), -1);
    }

    public function testBarrierManagerAwaitForCounter()
    {
        $barrierKey = uniqid();
        $parties = 10000;
        $callables = [];
        $waitMs = rand(100, 1000);

        for ($i = 0; $i < $parties - 1; ++$i) {
            $callables[] = static function () use ($barrierKey, $parties) {
                $startAt = microtime(true);
                BarrierManager::awaitForCounter($barrierKey, $parties);
                $resumeAt = microtime(true);
                return $resumeAt - $startAt;
            };
        }
        $callables[] = static function () use ($barrierKey, $parties, $waitMs) {
            usleep($waitMs * 1000);
            BarrierManager::awaitForCounter($barrierKey, $parties);
        };

        $retAt = array_filter(parallel($callables));
        $this->assertCount($parties - 1, $retAt);
        $minAt = min($retAt);

        $this->assertGreaterThanOrEqual($waitMs, (int) ($minAt * 1000));
        $this->assertEmpty(BarrierManager::list());
    }

    public function testBarrierManagerAwaitForCounterWithTimeout()
    {
        $barrierKey = uniqid();
        $parties = 2;

        parallel([
            function () use ($barrierKey, $parties) {
                try {
                    BarrierManager::awaitForCounter($barrierKey, $parties, 0.1);
                } catch (Exception $exception) {
                    $this->assertInstanceOf(TimeoutException::class, $exception);
                }
            },
            function () use ($barrierKey, $parties) {
                usleep(150 * 1000);
                BarrierManager::awaitForCounter($barrierKey, $parties, 0.1);
            },
            function () use ($barrierKey, $parties) {
                usleep(200 * 1000);
                BarrierManager::awaitForCounter($barrierKey, $parties, 0.1);
            },
        ]);

        $this->assertEmpty(BarrierManager::list());
    }

    public function testBarrierManagerAwaitOnCounterWithMultiBatch()
    {
        $stub = static function (string $barrierKey, $parties, $waitMs) {
            return static function () use ($barrierKey, $parties, $waitMs) {
                usleep($waitMs) * 1000;
                BarrierManager::awaitForCounter($barrierKey, $parties);
            };
        };

        $barrierKey = uniqid();
        $parties = 2;
        $callables = [];
        for ($i = 0; $i < 5000; ++$i) {
            for ($j = 0; $j < 2; ++$j) {
                $callables[] = $stub($barrierKey, $parties, mt_rand(100, 1000));
            }
        }
        parallel($callables);

        $this->assertEmpty(BarrierManager::list());
    }
}
