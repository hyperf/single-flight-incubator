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
use Hyperf\Incubator\Barrier\Context;
use Hyperf\Incubator\Barrier\Exception\RuntimeException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function Hyperf\Coroutine\parallel;

/**
 * @internal
 */
#[CoversNothing]
class BarrierAspectTest extends TestCase
{
    protected function tearDown(): void
    {
        Context::clearAll();
        AnnotationCollector::clear();
    }

    public function testAspectProcess()
    {
        $mockBarrier = new Barrier('#{a}_#{b}', 2);
        AnnotationCollector::set('MockClass._m.mockMethod.' . Barrier::class, $mockBarrier);

        $point = new ProceedingJoinPoint(
            static fn () => microtime(true),
            'MockClass',
            'mockMethod',
            ['keys' => ['a' => 1, 'b' => 2]]
        );
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

    public function testResolvePartiesPriority()
    {
        $aspect = new BarrierAspect();
        $reflection = new ReflectionClass($aspect);
        $partiesMethod = $reflection->getMethod('parties');
        $partiesMethod->setAccessible(true);

        $this->assertEquals(5, $partiesMethod->invoke($aspect, 5, 3, 2));

        $this->assertEquals(3, $partiesMethod->invoke($aspect, 0, 3, 2));

        $this->assertEquals(2, $partiesMethod->invoke($aspect, 0, 0, 2));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No valid Barrier annotation parties property resolved');
        $partiesMethod->invoke($aspect, 0, 0, 0);
    }

    public function testResolveBarrierKey()
    {
        $aspect = new BarrierAspect();
        $reflection = new ReflectionClass($aspect);
        $barrierKeyMethod = $reflection->getMethod('barrierKey');
        $barrierKeyMethod->setAccessible(true);

        $args = ['userId' => 123, 'action' => 'login'];
        $key = $barrierKeyMethod->invoke($aspect, 'user_#{userId}_#{action}', $args, 'context_key');
        $this->assertEquals('user_123_login', $key);

        $args = ['barrierKey' => 'method_key', 'other' => 'value'];
        $key = $barrierKeyMethod->invoke($aspect, '', $args, 'context_key');
        $this->assertEquals('method_key', $key);

        $args = ['other' => 'value'];
        $key = $barrierKeyMethod->invoke($aspect, '', $args, 'context_key');
        $this->assertEquals('context_key', $key);
    }

    public function testBarrierKeyTemplateWithNestedProperties()
    {
        $aspect = new BarrierAspect();
        $reflection = new ReflectionClass($aspect);
        $barrierKeyMethod = $reflection->getMethod('barrierKey');
        $barrierKeyMethod->setAccessible(true);

        $args = [
            'user' => ['id' => 123, 'name' => 'foo'],
            'config' => ['env' => 'prod'],
        ];

        $key = $barrierKeyMethod->invoke($aspect, 'app_#{user.id}_#{user.name}_#{config.env}', $args, 'context_key');
        $this->assertEquals('app_123_foo_prod', $key);
    }

    public function testResolvePartiesException()
    {
        $aspect = new BarrierAspect();
        $reflection = new ReflectionClass($aspect);
        $partiesMethod = $reflection->getMethod('parties');
        $partiesMethod->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No valid Barrier annotation parties property resolved');

        $partiesMethod->invoke($aspect, 0, 0, 0);
    }

    public function testResolveKeyException()
    {
        $aspect = new BarrierAspect();
        $reflection = new ReflectionClass($aspect);
        $barrierKeyMethod = $reflection->getMethod('barrierKey');
        $barrierKeyMethod->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No valid Barrier annotation value property resolved');

        $barrierKeyMethod->invoke($aspect, '', [], '');
    }

    public function testResolveTimeoutPriority()
    {
        $aspect = new BarrierAspect();
        $reflection = new ReflectionClass($aspect);
        $timeoutMethod = $reflection->getMethod('timeout');
        $timeoutMethod->setAccessible(true);

        $this->assertEquals(10.0, $timeoutMethod->invoke($aspect, 10.0, 8.0, 7.0));

        $this->assertEquals(8.0, $timeoutMethod->invoke($aspect, -1, 8.0, 7.0));

        $this->assertEquals(7.0, $timeoutMethod->invoke($aspect, -1, -1, 7.0));

        $this->assertEquals(-1, $timeoutMethod->invoke($aspect, -1, -1, -1));
        $this->assertEquals(-1, $timeoutMethod->invoke($aspect, 0, 0, 0));
    }
}
