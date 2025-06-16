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

use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Incubator\Barrier\Annotation\Barrier;
use Hyperf\Incubator\Barrier\Aspect\BarrierAspect;
use Hyperf\Incubator\Barrier\BarrierManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

use function Hyperf\Coroutine\parallel;

/**
 * @internal
 */
#[CoversNothing]
class BarrierAspectTest extends TestCase
{
    protected function tearDown(): void
    {
        AnnotationCollector::clear();
    }

    public function testAspectProcess()
    {
        $mockBarrier = new Barrier('#{a}_#{b}', 2);
        AnnotationCollector::set('MockClass._m.mockMethod.' . Barrier::class, $mockBarrier);

        $point = new ProceedingJoinPoint(static fn () => microtime(true), 'MockClass', 'mockMethod', ['keys' => ['a' => 1, 'b' => 2]]);
        $point->pipe = static fn () => microtime(true);

        $aspect = new BarrierAspect();

        $callables = [];
        $sleepMs = mt_rand(100, 1000);
        $startAt = microtime(true);

        $callables[] = function () use ($aspect, $point, $sleepMs, $startAt) {
            $endAt = $aspect->process($point);
            $elapsed = $endAt - $startAt;
            $this->assertGreaterThanOrEqual($sleepMs, (int) ($elapsed * 1000));
        };
        $callables[] = static function () use ($aspect, $point, $sleepMs) {
            usleep($sleepMs * 1000);
            $aspect->process($point);
        };

        parallel($callables);

        $this->assertEmpty(BarrierManager::list());
    }
}
