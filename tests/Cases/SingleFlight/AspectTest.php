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

use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Incubator\SingleFlight\Annotation\SingleFlight;
use Hyperf\Incubator\SingleFlight\Aspect\SingleFlightAspect;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function Hyperf\Coroutine\parallel;

/**
 * @internal
 */
#[CoversNothing]
class AspectTest extends TestCase
{
    protected function tearDown(): void
    {
        AnnotationCollector::clear();
    }

    public function testBarrierKey()
    {
        $aspect = new SingleFlightAspect();
        $reflection = new ReflectionClass($aspect);
        $method = $reflection->getMethod('barrierKey');
        $method->setAccessible(true);

        $proceedingJoinPoint = $this->createMock(ProceedingJoinPoint::class);
        $proceedingJoinPoint->className = 'SingleFlightTestClass';
        $proceedingJoinPoint->methodName = 'testMethod';
        $proceedingJoinPoint->arguments = ['keys' => ['arg1' => 'arg1', 'arg2' => 'arg2']];

        $annotation = new SingleFlight('#{arg1}_#{arg2}');
        AnnotationCollector::collectMethod('SingleFlightTestClass', 'testMethod', SingleFlight::class, $annotation);
        $result = $method->invoke($aspect, $proceedingJoinPoint);

        $this->assertEquals('arg1_arg2', $result);
    }

    public function testAspectProcess()
    {
        $mock = new SingleFlight('#{a}_#{b}');
        AnnotationCollector::set('MockClass._m.mockMethod.' . SingleFlight::class, $mock);

        $sleepMs = mt_rand(100, 200);
        $callables = [];
        $aspect = new SingleFlightAspect();
        for ($i = 0; $i < 10000; ++$i) {
            $point = new ProceedingJoinPoint(static fn () => $this->fail('testAspectProcess failed'), 'MockClass', 'mockMethod', ['keys' => ['a' => 1, 'b' => 2]]);
            $point->pipe = static function () use ($sleepMs, $i) {
                usleep($sleepMs * 1000);
                return $i;
            };
            $callables[] = static fn () => $aspect->process($point);
        }

        $rets = parallel($callables);

        $this->assertCount(1, array_unique($rets));
    }
}
