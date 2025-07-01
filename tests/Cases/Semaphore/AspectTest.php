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

namespace HyperfTest\Incubator\Cases\Semaphore;

use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Incubator\Semaphore\Annotation\Semaphore;
use Hyperf\Incubator\Semaphore\Aspect\SemaphoreAspect;
use Hyperf\Incubator\Semaphore\Context;
use Hyperf\Incubator\Semaphore\Exception\RuntimeException;
use Hyperf\Incubator\Semaphore\SemaphoreManager;
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
        Context::clearAll();
        AnnotationCollector::clear();

        SemaphoreManager::clear();
        SemaphoreManager::$refs = null;
    }

    public function testResolveTokens()
    {
        $aspect = new SemaphoreAspect();
        $reflection = new ReflectionClass($aspect);
        $method = $reflection->getMethod('tokens');
        $method->setAccessible(true);

        $ret = $method->invoke($aspect, 5, 3);
        $this->assertEquals(5, $ret);

        $ret = $method->invoke($aspect, 1, 3);
        $this->assertEquals(3, $ret);

        $ret = $method->invoke($aspect, 1, 1);
        $this->assertEquals(1, $ret);
    }

    public function testResolveAcquire()
    {
        $aspect = new SemaphoreAspect();
        $reflection = new ReflectionClass($aspect);
        $method = $reflection->getMethod('acquire');
        $method->setAccessible(true);

        $result = $method->invoke($aspect, 3, 2);
        $this->assertEquals(3, $result);

        $result = $method->invoke($aspect, 1, 2);
        $this->assertEquals(2, $result);

        $result = $method->invoke($aspect, 1, 1);
        $this->assertEquals(1, $result);
    }

    public function testResolveTimeout()
    {
        $aspect = new SemaphoreAspect();
        $reflection = new ReflectionClass($aspect);
        $method = $reflection->getMethod('timeout');
        $method->setAccessible(true);

        $result = $method->invoke($aspect, 5.0, 3.0);
        $this->assertEquals(5.0, $result);

        $result = $method->invoke($aspect, -1.0, 3.0);
        $this->assertEquals(3.0, $result);

        $result = $method->invoke($aspect, -1.0, -1.0);
        $this->assertEquals(-1.0, $result);
    }

    public function testResolveMethodArgKey()
    {
        $aspect = new SemaphoreAspect();
        $reflection = new ReflectionClass($aspect);
        $method = $reflection->getMethod('key');
        $method->setAccessible(true);

        $key = uniqid();
        $args = [SemaphoreAspect::ARG_KEY => $key];
        $result = $method->invoke($aspect, '', $args);
        $this->assertEquals($key, $result);
    }

    public function testResolveContextKey()
    {
        $aspect = new SemaphoreAspect();
        $reflection = new ReflectionClass($aspect);
        $method = $reflection->getMethod('key');
        $method->setAccessible(true);

        $key = uniqid();
        Context::withKey($key);

        $result = $method->invoke($aspect, '', []);
        $this->assertEquals($key, $result);
    }

    public function testResolveKeyException()
    {
        $aspect = new SemaphoreAspect();
        $reflection = new ReflectionClass($aspect);
        $method = $reflection->getMethod('key');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No valid annotation key argument resolved');

        $method->invoke($aspect, '', []);
    }

    public function testKeyTemplateWithNestedProperties()
    {
        $aspect = new SemaphoreAspect();
        $reflection = new ReflectionClass($aspect);
        $method = $reflection->getMethod('key');
        $method->setAccessible(true);

        $args = [
            'user' => ['id' => 123, 'name' => 'test'],
            'action' => 'update',
        ];

        $result = $method->invoke($aspect, 'user_#{user.id}_#{action}', $args);
        $this->assertEquals('user_123_update', $result);
    }

    public function testParameterPriorityWithContext()
    {
        $aspect = new SemaphoreAspect();
        $reflection = new ReflectionClass($aspect);
        $tokensMethod = $reflection->getMethod('tokens');
        $tokensMethod->setAccessible(true);

        Context::withTokens(10);

        $result = $tokensMethod->invoke($aspect, 5, 1);
        $this->assertEquals(5, $result);

        $result = $tokensMethod->invoke($aspect, 1, 3);
        $this->assertEquals(3, $result);

        $result = $tokensMethod->invoke($aspect, 1, 1);
        $this->assertEquals(10, $result);
    }

    public function testSemaphoreKey()
    {
        $aspect = new SemaphoreAspect();
        $reflection = new ReflectionClass($aspect);
        $method = $reflection->getMethod('key');
        $method->setAccessible(true);

        $proceedingJoinPoint = $this->createMock(ProceedingJoinPoint::class);
        $proceedingJoinPoint->className = 'SemaphoreTestClass';
        $proceedingJoinPoint->methodName = 'testMethod';
        $proceedingJoinPoint->arguments = ['keys' => ['arg1' => 'arg1', 'arg2' => 'arg2']];

        $annotation = new Semaphore('#{arg1}_#{arg2}');
        AnnotationCollector::collectMethod('SemaphoreTestClass', 'testMethod', Semaphore::class, $annotation);
        $result = $method->invoke($aspect, '#{arg1}_#{arg2}', ['arg1' => 'arg1', 'arg2' => 'arg2']);

        $this->assertEquals('arg1_arg2', $result);
    }

    public function testAspectProcess()
    {
        $mock = new Semaphore('#{a}_#{b}', tokens: 1);
        AnnotationCollector::set('MockClass._m.mockMethod.' . Semaphore::class, $mock);

        $sleepMs = mt_rand(50, 100);
        $callables = [];
        $aspect = new SemaphoreAspect();
        $callables[] = function () use ($sleepMs, $aspect) {
            $point = new ProceedingJoinPoint(fn () => $this->fail('testAspectProcess failed'), 'MockClass', 'mockMethod', ['keys' => ['a' => 1, 'b' => 2]]);
            $point->pipe = static function () use ($sleepMs) {
                usleep($sleepMs * 1000);
            };
            $aspect->process($point);
        };

        $elapsed = 0;
        $callables[] = static function () use ($aspect, &$elapsed) {
            $startAt = microtime(true);
            $point = new ProceedingJoinPoint(fn () => $this->fail('testAspectProcess failed'), 'MockClass', 'mockMethod', ['keys' => ['a' => 1, 'b' => 2]]);
            $point->pipe = static function () use ($startAt, &$elapsed) {
                $endAt = microtime(true);
                $elapsed = (int) (($endAt - $startAt) * 1000);
            };
            $aspect->process($point);
        };

        parallel($callables);

        $this->assertGreaterThanOrEqual($sleepMs * 9 / 10, $elapsed);
    }

    public function testManagerSameSema()
    {
        $key = uniqid();
        $tokens = mt_rand(1, 100);
        $sema1 = SemaphoreManager::getSema($key, $tokens);
        $sema2 = SemaphoreManager::getSema($key, $tokens);
        $this->assertSame($sema1, $sema2);
    }

    public function testManagerRemove()
    {
        $this->assertTrue(SemaphoreManager::remove(uniqid()));

        $key = uniqid();
        SemaphoreManager::getSema($key, 123);
        $this->assertTrue(SemaphoreManager::remove($key));

        $key = uniqid();
        SemaphoreManager::getSema($key, 456);
        SemaphoreManager::getSema($key, 456);
        $this->assertFalse(SemaphoreManager::remove($key));
    }

    public function testManagerAfterRemove()
    {
        $callables = [];
        $round = mt_rand(5, 10);
        for ($i = 0; $i < $round; ++$i) {
            $key = uniqid();
            $tokens = mt_rand(50, 100);
            for ($i = 0; $i < $tokens; ++$i) {
                $callables[] = static function () use ($key, $tokens) {
                    $sema = SemaphoreManager::getSema($key, $tokens);
                    $sema->acquire(1);
                    SemaphoreManager::remove($key);
                };
            }
        }

        parallel($callables);

        $this->assertEmpty(SemaphoreManager::list());
        $this->assertEmpty(SemaphoreManager::$refs);
    }

    public function testManagerAfterAspectRemove()
    {
        $tokens = 5;
        $mock = new Semaphore('#{a}_#{b}', $tokens);
        AnnotationCollector::set('MockClass._m.mockMethod.' . Semaphore::class, $mock);

        $callables = [];
        $aspect = new SemaphoreAspect();
        for ($i = 0; $i < $tokens; ++$i) {
            $callables[] = function () use ($aspect, $i) {
                $point = new ProceedingJoinPoint(fn () => "result_{$i}", 'MockClass', 'mockMethod', ['keys' => ['a' => 1, 'b' => 2]]);
                $point->pipe = static fn ($result) => $result;
                return $aspect->process($point);
            };
        }
        parallel($callables);

        $this->assertEmpty(SemaphoreManager::list());
        $this->assertEmpty(SemaphoreManager::$refs);
    }
}
