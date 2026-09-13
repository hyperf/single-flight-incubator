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
use Hyperf\Incubator\Semaphore\Exception\TimeoutException;
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

        $ret = $method->invoke($aspect, 5, 3, 2);
        $this->assertEquals(5, $ret);

        // an explicit annotation value of 1 wins over the lower levels
        $ret = $method->invoke($aspect, 1, 3, 2);
        $this->assertEquals(1, $ret);

        $ret = $method->invoke($aspect, 0, 3, 2);
        $this->assertEquals(3, $ret);

        $ret = $method->invoke($aspect, 0, 0, 2);
        $this->assertEquals(2, $ret);

        $ret = $method->invoke($aspect, 0, 0, 0);
        $this->assertEquals(1, $ret);
    }

    public function testResolveAcquire()
    {
        $aspect = new SemaphoreAspect();
        $reflection = new ReflectionClass($aspect);
        $method = $reflection->getMethod('acquire');
        $method->setAccessible(true);

        $result = $method->invoke($aspect, 3, 2, 4);
        $this->assertEquals(3, $result);

        // an explicit annotation value of 1 wins over the lower levels
        $result = $method->invoke($aspect, 1, 2, 4);
        $this->assertEquals(1, $result);

        $result = $method->invoke($aspect, 0, 2, 4);
        $this->assertEquals(2, $result);

        $result = $method->invoke($aspect, 0, 0, 4);
        $this->assertEquals(4, $result);

        $result = $method->invoke($aspect, 0, 0, 0);
        $this->assertEquals(1, $result);
    }

    public function testResolveTimeout()
    {
        $aspect = new SemaphoreAspect();
        $reflection = new ReflectionClass($aspect);
        $method = $reflection->getMethod('timeout');
        $method->setAccessible(true);

        $result = $method->invoke($aspect, 5.0, 3.0, 2.0);
        $this->assertEquals(5.0, $result);

        $result = $method->invoke($aspect, -1.0, 3.0, 2.0);
        $this->assertEquals(3.0, $result);

        $result = $method->invoke($aspect, 0.0, 3.0, 2.0);
        $this->assertEquals(3.0, $result);

        $result = $method->invoke($aspect, -1.0, -1.0, 2.0);
        $this->assertEquals(2.0, $result);

        $result = $method->invoke($aspect, 0.0, 0.0, 2.0);
        $this->assertEquals(2.0, $result);

        $result = $method->invoke($aspect, -1.0, -1.0, -1.0);
        $this->assertEquals(-1.0, $result);

        $result = $method->invoke($aspect, 0.0, 0.0, 0.0);
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
        $result = $method->invoke($aspect, '', $args, '');
        $this->assertEquals($key, $result);
    }

    public function testResolveContextKey()
    {
        $aspect = new SemaphoreAspect();
        $reflection = new ReflectionClass($aspect);
        $method = $reflection->getMethod('key');
        $method->setAccessible(true);

        $key = uniqid();
        $result = $method->invoke($aspect, '', [], $key);
        $this->assertEquals($key, $result);
    }

    public function testResolveKeyException()
    {
        $aspect = new SemaphoreAspect();
        $reflection = new ReflectionClass($aspect);
        $method = $reflection->getMethod('key');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No valid Semaphore annotation key property resolved');

        $method->invoke($aspect, '', [], '');
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

        $result = $method->invoke($aspect, 'user_#{user.id}_#{action}', $args, '');
        $this->assertEquals('user_123_update', $result);
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
        $result = $method->invoke($aspect, '#{arg1}_#{arg2}', ['arg1' => 'arg1', 'arg2' => 'arg2'], '');

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

    public function testManagerRemoveWithConcurrentRefs()
    {
        $key = uniqid();
        $tokens = mt_rand(1, 100);

        $sema1 = SemaphoreManager::getSema($key, $tokens);
        $sema2 = SemaphoreManager::getSema($key, $tokens);
        $this->assertSame($sema1, $sema2);

        // concurrent holders keep the entry alive, sharing the same semaphore
        $this->assertFalse(SemaphoreManager::remove($key));
        $this->assertSame($sema1, SemaphoreManager::getSema($key, $tokens));

        // the entry is removed only after the last reference is dropped
        $this->assertFalse(SemaphoreManager::remove($key));
        $this->assertTrue(SemaphoreManager::remove($key));
        $this->assertEmpty(SemaphoreManager::list());
    }

    public function testManagerRefsEvaporateWithSemaphoreLifetime()
    {
        $key = uniqid();
        $sema = SemaphoreManager::getSema($key, 1);
        $this->assertCount(1, SemaphoreManager::$refs);

        SemaphoreManager::clear();
        // the semaphore object is still alive, so is the refcount entry
        $this->assertCount(1, SemaphoreManager::$refs);

        // dropping the last strong reference evicts the WeakMap entry automatically
        unset($sema);
        $this->assertEmpty(SemaphoreManager::$refs);
    }

    public function testManagerDoesNotLeakAcrossCycles()
    {
        $cycle = static function (int $rounds): void {
            for ($i = 0; $i < $rounds; ++$i) {
                $key = uniqid();
                $sema = SemaphoreManager::getSema($key, 1);
                $sema->acquire(1);
                $sema->release(1);
                SemaphoreManager::remove($key);
            }
        };

        $cycle(100);
        $this->assertEmpty(SemaphoreManager::list());
        $this->assertEmpty(SemaphoreManager::$refs);
        $before = memory_get_usage();

        $cycle(2000);
        gc_collect_cycles();

        // a real leak would retain one semaphore per cycle, far beyond this bound
        $this->assertLessThan(256 * 1024, memory_get_usage() - $before);
        $this->assertEmpty(SemaphoreManager::list());
        $this->assertEmpty(SemaphoreManager::$refs);
    }

    public function testManagerStaleRefsDoNotAffectNewGeneration()
    {
        $key = uniqid();
        $stale = SemaphoreManager::getSema($key, 1);
        $this->assertTrue(SemaphoreManager::remove($key));
        // the stale object is still held here, so its zero-count entry survives
        $this->assertSame(0, SemaphoreManager::$refs[$stale]);

        // a new generation takes the key over, with its own refcount
        $fresh = SemaphoreManager::getSema($key, 1);
        $this->assertNotSame($stale, $fresh);
        $this->assertSame(1, SemaphoreManager::$refs[$fresh]);

        // redundant removes never push any count negative
        $this->assertTrue(SemaphoreManager::remove($key));
        $this->assertTrue(SemaphoreManager::remove($key));
        $this->assertSame(0, SemaphoreManager::$refs[$fresh]);

        // entries strictly follow their objects' lifetime
        unset($fresh);
        $this->assertCount(1, SemaphoreManager::$refs);
        unset($stale);
        $this->assertEmpty(SemaphoreManager::$refs);
        $this->assertEmpty(SemaphoreManager::list());
    }

    public function testManagerCleanupWithTimedOutHolders()
    {
        $tokens = 2;
        $key = uniqid();
        $peak = 0;
        $active = 0;

        $callables = [];
        // holders occupy the semaphore longer than the waiters' timeout
        for ($i = 0; $i < $tokens; ++$i) {
            $callables[] = static function () use ($key, $tokens, &$peak, &$active) {
                $sema = SemaphoreManager::getSema($key, $tokens);
                $sema->acquire(1);
                ++$active;
                $peak = max($peak, $active);
                usleep(200 * 1000);
                --$active;
                $sema->release(1);
                SemaphoreManager::remove($key);
            };
        }
        // waiters queue up, time out, and remove like the aspect does
        for ($i = 0; $i < 5; ++$i) {
            $callables[] = static function () use ($key, $tokens, &$peak, &$active) {
                $sema = SemaphoreManager::getSema($key, $tokens);
                try {
                    $sema->acquire(1, 0.05);
                    ++$active;
                    $peak = max($peak, $active);
                    --$active;
                    $sema->release(1);
                } catch (TimeoutException) {
                    // timed out, the aspect skips the release
                }
                SemaphoreManager::remove($key);
            };
        }
        parallel($callables);

        // the entry was never duplicated, so the token limit held
        $this->assertLessThanOrEqual($tokens, $peak);
        // and the storm left nothing behind
        $this->assertEmpty(SemaphoreManager::list());
        $this->assertEmpty(SemaphoreManager::$refs);
    }
}
