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

use Hyperf\Engine\Channel;
use Hyperf\Incubator\SingleFlight\Exception\RuntimeException;
use Hyperf\Incubator\SingleFlight\Exception\TimeoutException;
use Hyperf\Incubator\SingleFlight\SingleFlight;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Throwable;

use function Hyperf\Coroutine\go;
use function Hyperf\Coroutine\parallel;

/**
 * @internal
 */
#[CoversNothing]
class SingeFlightTest extends TestCase
{
    public function testSingeFlight()
    {
        $barrierKey = uniqid();
        $ret = [];
        $callables = [];
        $range = range(1, 10000);
        foreach ($range as $v) {
            $callables[] = static function () use ($barrierKey, $v, &$ret) {
                $ret[] = SingleFlight::do($barrierKey, static function () use ($v) {
                    // ensure that other coroutines can be scheduled at the same time
                    usleep(1000);
                    return $v;
                });
            };
        }
        parallel($callables, count($callables));

        $this->assertCount(count($range), $ret);
        $this->assertCount(1, array_unique($ret));
        $this->assertEmpty(SingleFlight::list());
    }

    public function testSingeFlightWithRuntimeException()
    {
        $barrierKey = uniqid();
        $ret = [];
        $callables = [];
        $range = range(1, 10000);
        foreach ($range as $v) {
            $callables[] = static function () use ($barrierKey, $v, &$ret) {
                try {
                    SingleFlight::do($barrierKey, static function () use ($v) {
                        // ensure that other coroutines can be scheduled at the same time
                        usleep(1000);
                        throw new \RuntimeException('from ' . $v);
                    });
                } catch (RuntimeException $e) {
                    $ret[] = $e->getPrevious()->getMessage();
                }
            };
        }
        parallel($callables, count($callables));

        $this->assertCount(count($range), $ret);
        $this->assertCount(1, array_unique($ret));
        $this->assertEmpty(SingleFlight::list());
    }

    public function testSingeFlightWithTimeoutException()
    {
        $barrierKey = uniqid();
        $ret = [];
        $callables = [];
        $range = range(1, 10000);
        foreach ($range as $v) {
            $callables[] = static function () use ($barrierKey, $v, &$ret) {
                try {
                    SingleFlight::do($barrierKey, static function () use ($v, &$ret) {
                        // ensure that other coroutines can be scheduled at the same time
                        usleep(2000 * 1000);
                        $ret[] = $v;
                        return $v;
                    }, 0.1);
                } catch (Throwable $e) {
                    if ($e instanceof TimeoutException) {
                        $ret[] = $e->getMessage();
                    }
                }
            };
        }
        parallel($callables, count($callables));

        $this->assertCount(count($range), $ret);
        $this->assertCount(2, array_unique($ret));

        $iRet = array_filter($ret, static fn ($v) => is_int($v));
        $this->assertCount(1, $iRet);
        $this->assertEmpty(SingleFlight::list());
    }

    public function testSingleFlightWithForgetWithoutWaiters()
    {
        $barrierKey = uniqid();
        $wanted = 'expected';

        $ret = SingleFlight::do($barrierKey, fn () => $wanted);
        $this->assertSame($wanted, $ret);
        $this->assertEmpty(SingleFlight::list());

        SingleFlight::forget($barrierKey);

        $wanted .= '_after_forget';
        $ret = SingleFlight::do($barrierKey, fn () => $wanted);
        $this->assertSame($wanted, $ret);
        $this->assertEmpty(SingleFlight::list());

        $beforeForget = 'before_forget';
        go(function () use ($barrierKey) {
            usleep(500);
            SingleFlight::forget($barrierKey);
        });
        $ret = SingleFlight::do($barrierKey, function () use ($beforeForget) {
            usleep(1000);
            return $beforeForget;
        });
        $this->assertSame($beforeForget, $ret);
        $this->assertEmpty(SingleFlight::list());
    }

    public function testSingleFlightWithForgetWithWaiters()
    {
        $barrierKey = uniqid();
        $ret = [];
        $callables = [];
        $doTimes = 1000;
        $forgetTimes = mt_rand(1, 500);
        $allTimes = $doTimes + $forgetTimes;

        for ($i = 0; $i < $allTimes; ++$i) {
            if ($i < $doTimes) {
                $callables[] = static function () use ($barrierKey, $i, &$ret) {
                    $ret[] = SingleFlight::do($barrierKey, static function () use ($i, &$ret) {
                        // ensure that other coroutines can be scheduled at the same time
                        usleep(200 * 1000);
                        return $i;
                    });
                };
            } else {
                $callables[] = static function () use ($barrierKey) {
                    $sleepMs = 50 + mt_rand(0, 50);
                    usleep($sleepMs);
                    SingleFlight::forget($barrierKey);
                };
            }
        }

        shuffle($callables);

        parallel($callables);

        $this->assertCount($doTimes, $ret);
        $this->assertCount($forgetTimes + 1, array_unique($ret));
        $this->assertEmpty(SingleFlight::list());
    }

    public function testSingleFlightWithForgetRetryAndLateArrival()
    {
        $barrierKey = uniqid();
        $generations = new Channel(10);

        // the first generation leader runs a long processor
        go(static function () use ($barrierKey, $generations): void {
            SingleFlight::do($barrierKey, static function () use ($generations) {
                $generations->push('g1');
                usleep(400 * 1000);
                return 'r1';
            });
        });
        $this->assertSame('g1', $generations->pop());

        // a waiter whose processor runs even longer, it retries into the second generation
        go(static function () use ($barrierKey, $generations): void {
            SingleFlight::do($barrierKey, static function () use ($generations) {
                $generations->push('g2');
                usleep(800 * 1000);
                return 'r2';
            }, 1.0);
        });
        usleep(50 * 1000);

        SingleFlight::forget($barrierKey);
        $this->assertSame('g2', $generations->pop());

        // the first leader finished by now, while the second generation is still running
        usleep(550 * 1000);

        // the late arrival must wait for the running second generation
        // instead of leading a third concurrent execution
        $this->assertSame('r2', SingleFlight::do($barrierKey, static fn () => 'r3', 1.0));
    }
}
