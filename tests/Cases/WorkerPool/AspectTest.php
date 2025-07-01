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

namespace HyperfTest\Incubator\Cases\WorkerPool;

use Hyperf\Coroutine\Coroutine;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Incubator\WorkerPool\Annotation\WorkerPool;
use Hyperf\Incubator\WorkerPool\Aspect\WorkerPoolAspect;
use Hyperf\Incubator\WorkerPool\Config;
use Hyperf\Incubator\WorkerPool\Context;
use Hyperf\Incubator\WorkerPool\Exception\RuntimeException;
use Hyperf\Incubator\WorkerPool\WorkerPoolManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
#[CoversNothing]
class AspectTest extends TestCase
{
    protected function tearDown(): void
    {
        Context::clearAll();
        AnnotationCollector::clear();
        WorkerPoolManager::removeAll();
    }

    public function testNamePriority()
    {
        $aspect = new WorkerPoolAspect();
        $reflection = new ReflectionClass(WorkerPoolAspect::class);
        $nameMethod = $reflection->getMethod('name');
        $nameMethod->setAccessible(true);

        $args = ['workerPoolName' => 'arg-name'];
        $result = $nameMethod->invoke($aspect, 'anno-name', $args, 'context-name');
        $this->assertEquals('anno-name', $result);

        $args = ['test' => 'replaced'];
        $result = $nameMethod->invoke($aspect, 'prefix-#{test}-suffix', $args, 'context-name');
        $this->assertEquals('prefix-replaced-suffix', $result);

        $args = ['workerPoolName' => 'arg-name'];
        $result = $nameMethod->invoke($aspect, '', $args, 'context-name');
        $this->assertEquals('arg-name', $result);

        $args = [];
        $result = $nameMethod->invoke($aspect, '', $args, 'context-name');
        $this->assertEquals('context-name', $result);

        $args = [];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No valid WorkerPool annotation name property resolved');
        $nameMethod->invoke($aspect, '', $args, '');
    }

    public function testTimeoutPriority()
    {
        $aspect = new WorkerPoolAspect();
        $reflection = new ReflectionClass(WorkerPoolAspect::class);
        $timeoutMethod = $reflection->getMethod('timeout');
        $timeoutMethod->setAccessible(true);

        $result = $timeoutMethod->invoke($aspect, 10.0, 20.0, 30.0);
        $this->assertEquals(10.0, $result);

        $result = $timeoutMethod->invoke($aspect, 0.0, 20.0, 30.0);
        $this->assertEquals(20.0, $result);

        $result = $timeoutMethod->invoke($aspect, -1.0, 20.0, 30.0);
        $this->assertEquals(20.0, $result);

        $result = $timeoutMethod->invoke($aspect, 0.0, 0.0, 30.0);
        $this->assertEquals(30.0, $result);

        $result = $timeoutMethod->invoke($aspect, -1.0, -1.0, 30.0);
        $this->assertEquals(30.0, $result);

        $result = $timeoutMethod->invoke($aspect, 0.0, 0.0, 0.0);
        $this->assertEquals(-1, $result);

        $result = $timeoutMethod->invoke($aspect, -1.0, -1.0, -1.0);
        $this->assertEquals(-1, $result);

        $result = $timeoutMethod->invoke($aspect, 0.0, -1.0, -1.0);
        $this->assertEquals(-1, $result);
    }

    public function testSyncPriority()
    {
        $aspect = new WorkerPoolAspect();
        $reflection = new ReflectionClass(WorkerPoolAspect::class);
        $syncMethod = $reflection->getMethod('sync');
        $syncMethod->setAccessible(true);

        $result = $syncMethod->invoke($aspect, true, false, false);
        $this->assertFalse($result);

        $result = $syncMethod->invoke($aspect, true, true, false);
        $this->assertFalse($result);

        $result = $syncMethod->invoke($aspect, true, false, true);
        $this->assertFalse($result);

        $result = $syncMethod->invoke($aspect, true, true, true);
        $this->assertTrue($result);

        $result = $syncMethod->invoke($aspect, false, true, false);
        $this->assertFalse($result);

        $result = $syncMethod->invoke($aspect, false, false, true);
        $this->assertFalse($result);

        $result = $syncMethod->invoke($aspect, true, true, false);
        $this->assertFalse($result);

        $result = $syncMethod->invoke($aspect, false, true, false);
        $this->assertFalse($result);
    }

    public function testProcess()
    {
        $pool = new \Hyperf\Incubator\WorkerPool\WorkerPool(new Config());
        WorkerPoolManager::setPool('1_2', $pool);

        $mock = new WorkerPool('#{a}_#{b}');
        AnnotationCollector::set('MockClass._m.mockMethod.' . WorkerPool::class, $mock);
        $point = new ProceedingJoinPoint(fn () => $this->fail('testAspectProcess failed'), 'MockClass', 'mockMethod', ['keys' => ['a' => 1, 'b' => 2]]);
        $point->pipe = static fn (): int => Coroutine::id();
        $aspect = new WorkerPoolAspect();
        $id = $aspect->process($point);

        $this->assertNotEquals($id, Coroutine::id());
    }

    public function testProcessException()
    {
        $pool = new \Hyperf\Incubator\WorkerPool\WorkerPool(new Config());
        WorkerPoolManager::setPool('3_4', $pool);

        $mock = new WorkerPool('#{a}_#{b}');
        AnnotationCollector::set('MockClass._m.mockMethod.' . WorkerPool::class, $mock);
        $point = new ProceedingJoinPoint(fn () => $this->fail('testAspectProcess failed'), 'MockClass', 'mockMethod', ['keys' => ['a' => 1, 'b' => 2]]);
        $aspect = new WorkerPoolAspect();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No pool named 1_2 found');

        $aspect->process($point);
    }
}
