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
        Context::clearAll();

        $aspect = new BarrierAspect();

        $mockBarrier = new Barrier('test_key', 5, 10.0);
        AnnotationCollector::set('TestPartiesClass._m.testMethod.' . Barrier::class, $mockBarrier);

        $point = new ProceedingJoinPoint(
            static fn () => 'ret',
            'TestPartiesClass',
            'testMethod',
            ['keys' => [BarrierAspect::ARG_PARTIES => 3, BarrierAspect::ARG_TIMEOUT => 8.0]]
        );
        $point->pipe = static fn () => 'ret';

        Context::withParties(2);
        Context::withTimeout(7.0);

        $reflection = new ReflectionClass($aspect);
        $partiesMethod = $reflection->getMethod('parties');
        $partiesMethod->setAccessible(true);
        $timeoutMethod = $reflection->getMethod('timeout');
        $timeoutMethod->setAccessible(true);

        $this->assertEquals(5, $partiesMethod->invoke($aspect, 5, 3));
        $this->assertEquals(10.0, $timeoutMethod->invoke($aspect, 10.0, 8.0));

        $this->assertEquals(3, $partiesMethod->invoke($aspect, 0, 3));
        $this->assertEquals(8.0, $timeoutMethod->invoke($aspect, -1, 8.0));

        $this->assertEquals(2, $partiesMethod->invoke($aspect, 0, 0));
        $this->assertEquals(7.0, $timeoutMethod->invoke($aspect, -1, -1));
    }

    public function testResolveBarrierKey()
    {
        Context::clearAll();

        $aspect = new BarrierAspect();
        $reflection = new ReflectionClass($aspect);
        $barrierKeyMethod = $reflection->getMethod('barrierKey');
        $barrierKeyMethod->setAccessible(true);

        $point = new ProceedingJoinPoint(
            static fn () => 'ret',
            'TestKeyClass',
            'testMethod',
            ['keys' => ['userId' => 123, 'action' => 'login']]
        );

        $key = $barrierKeyMethod->invoke($aspect, 'user_#{userId}_#{action}', $point);
        $this->assertEquals('user_123_login', $key);

        $point2 = new ProceedingJoinPoint(
            static fn () => 'ret',
            'TestKeyClass',
            'testMethod',
            ['keys' => ['barrierKey' => 'method_key']]
        );

        Context::withKey('context_key');

        $key = $barrierKeyMethod->invoke($aspect, '', $point2);
        $this->assertEquals('method_key', $key);

        $point3 = new ProceedingJoinPoint(
            static fn () => 'ret',
            'TestKeyClass',
            'testMethod',
            ['keys' => []]
        );

        $key = $barrierKeyMethod->invoke($aspect, '', $point3);
        $this->assertEquals('context_key', $key);
    }

    public function testBarrierKeyTemplateWithNestedProperties()
    {
        Context::clearAll();

        $aspect = new BarrierAspect();
        $reflection = new ReflectionClass($aspect);
        $barrierKeyMethod = $reflection->getMethod('barrierKey');
        $barrierKeyMethod->setAccessible(true);

        $point = new ProceedingJoinPoint(
            static fn () => 'result',
            'TestTplClass',
            'testMethod',
            ['keys' => [
                'user' => ['id' => 123, 'name' => 'foo'],
                'config' => ['env' => 'prod'],
            ]]
        );

        $key = $barrierKeyMethod->invoke($aspect, 'app_#{user.id}_#{user.name}_#{config.env}', $point);
        $this->assertEquals('app_123_foo_prod', $key);
    }

    public function testResolvePartiesException()
    {
        Context::clearAll();

        $aspect = new BarrierAspect();
        $reflection = new ReflectionClass($aspect);
        $partiesMethod = $reflection->getMethod('parties');
        $partiesMethod->setAccessible(true);

        Context::withParties(0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No valid annotation parties argument resolved');

        $partiesMethod->invoke($aspect, 0, 0);
    }

    public function testResolveKeyException()
    {
        Context::clearAll();

        $aspect = new BarrierAspect();
        $reflection = new ReflectionClass($aspect);
        $barrierKeyMethod = $reflection->getMethod('barrierKey');
        $barrierKeyMethod->setAccessible(true);

        $point = new ProceedingJoinPoint(
            static fn () => 'result',
            'TestNoValidKeyClass',
            'testMethod',
            ['keys' => []]
        );

        Context::withKey('');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No valid annotation value argument resolved');

        $barrierKeyMethod->invoke($aspect, '', $point);
    }

    public function testResolveTimeoutFallback()
    {
        Context::clearAll();

        $aspect = new BarrierAspect();
        $reflection = new ReflectionClass($aspect);
        $timeoutMethod = $reflection->getMethod('timeout');
        $timeoutMethod->setAccessible(true);

        $ret = $timeoutMethod->invoke($aspect, -1, -1);
        $this->assertEquals(-1, $ret);

        $ret = $timeoutMethod->invoke($aspect, 0, 0);
        $this->assertEquals(-1, $ret);
    }
}
