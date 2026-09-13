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

namespace HyperfTest\Incubator\Cases\Barrier;

use Exception;
use Hyperf\Engine\Channel;
use Hyperf\Incubator\Barrier\BarrierManager;
use Hyperf\Incubator\Barrier\CounterBarrier;
use Hyperf\Incubator\Barrier\Exception\RuntimeException;
use Hyperf\Incubator\Barrier\Exception\TimeoutException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Throwable;

use function Hyperf\Coroutine\go;
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
            $callables[] = function () use ($barrier) {
                $startAt = microtime(true);
                $barrier->await();
                $resumeAt = microtime(true);
                return $resumeAt - $startAt;
            };
        }

        $callables[] = function () use ($barrier, $waitMs) {
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
        BarrierManager::counterCall(uniqid(), -1, $this->noneStub(...), -1);
    }

    public function testBarrierManagerAwaitForCounter()
    {
        $barrierKey = uniqid();
        $parties = 10000;
        $callables = [];
        $waitMs = rand(100, 1000);

        for ($i = 0; $i < $parties - 1; ++$i) {
            $callables[] = function () use ($barrierKey, $parties) {
                $startAt = microtime(true);
                BarrierManager::counterCall($barrierKey, $parties, $this->noneStub(...));
                $resumeAt = microtime(true);
                return $resumeAt - $startAt;
            };
        }
        $callables[] = function () use ($barrierKey, $parties, $waitMs) {
            usleep($waitMs * 1000);
            BarrierManager::counterCall($barrierKey, $parties, $this->noneStub(...));
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
                    BarrierManager::counterCall($barrierKey, $parties, $this->noneStub(...), 0.1);
                } catch (Exception $exception) {
                    $this->assertInstanceOf(TimeoutException::class, $exception);
                }
            },
            function () use ($barrierKey, $parties) {
                usleep(150 * 1000);
                BarrierManager::counterCall($barrierKey, $parties, $this->noneStub(...), 0.1);
            },
            function () use ($barrierKey, $parties) {
                usleep(200 * 1000);
                BarrierManager::counterCall($barrierKey, $parties, $this->noneStub(...), 0.1);
            },
        ]);

        $this->assertEmpty(BarrierManager::list());
    }

    public function testBarrierManagerAwaitOnCounterWithMultiBatch()
    {
        $stub = function (string $barrierKey, $parties, $waitMs) {
            return function () use ($barrierKey, $parties, $waitMs) {
                usleep($waitMs) * 1000;
                BarrierManager::counterCall($barrierKey, $parties, $this->noneStub(...));
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

    public function testBarrierManagerStartsNewGenerationWhilePreviousBatchRunning()
    {
        $barrierKey = uniqid();

        // both parties run long callers, so the tripped entry stays in the container
        go(static function () use ($barrierKey): void {
            BarrierManager::counterCall($barrierKey, 2, static fn (): string => usleep(400 * 1000) ? '' : 'a');
        });
        usleep(10 * 1000);
        go(static function () use ($barrierKey): void {
            BarrierManager::counterCall($barrierKey, 2, static fn (): string => usleep(400 * 1000) ? '' : 'b');
        });

        // the previous batch callers are still running, a new batch must start a new generation
        usleep(100 * 1000);
        $chan = new Channel(2);
        go(static function () use ($barrierKey, $chan): void {
            try {
                $chan->push(BarrierManager::counterCall($barrierKey, 2, static fn (): string => 'c', 1.5));
            } catch (Throwable $e) {
                $chan->push('ERR:' . $e->getMessage());
            }
        });
        go(static function () use ($barrierKey, $chan): void {
            try {
                $chan->push(BarrierManager::counterCall($barrierKey, 2, static fn (): string => 'd', 1.5));
            } catch (Throwable $e) {
                $chan->push('ERR:' . $e->getMessage());
            }
        });

        $this->assertEqualsCanonicalizing(['c', 'd'], [$chan->pop(3), $chan->pop(3)]);
    }

    public function testBarrierManagerDoesNotUnburyFormingGeneration()
    {
        $barrierKey = uniqid();

        // the first party finishes fast and cleans the tripped entry
        go(static function () use ($barrierKey): void {
            BarrierManager::counterCall($barrierKey, 2, static fn (): string => usleep(50 * 1000) ? '' : 'a');
        });
        usleep(10 * 1000);
        // the last party trips the barrier, and keeps running its caller until t=500
        go(static function () use ($barrierKey): void {
            BarrierManager::counterCall($barrierKey, 2, static fn (): string => usleep(500 * 1000) ? '' : 'c');
        });

        // t≈400: the tripped entry was cleaned, a new generation starts forming
        usleep(390 * 1000);
        $chan = new Channel(2);
        go(static function () use ($barrierKey, $chan): void {
            try {
                $chan->push('B:' . BarrierManager::counterCall($barrierKey, 2, static fn (): string => 'b', 2.0));
            } catch (Throwable $e) {
                $chan->push('B:' . get_class($e));
            }
        });

        // t≈600: the slow party's finally happened at t≈500, it must not unbury the forming barrier
        usleep(200 * 1000);
        go(static function () use ($barrierKey, $chan): void {
            try {
                $chan->push('D:' . BarrierManager::counterCall($barrierKey, 2, static fn (): string => 'd', 2.0));
            } catch (Throwable $e) {
                $chan->push('D:' . get_class($e));
            }
        });

        $this->assertSame('B:b', $chan->pop(3));
        $this->assertSame('D:d', $chan->pop(3));
    }

    private function noneStub()
    {
    }
}
