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

use Hyperf\Context\Context as HyperfContext;
use Hyperf\Incubator\Semaphore\Context;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

use function Hyperf\Coroutine\go;

/**
 * @internal
 */
#[CoversNothing]
class ContextTest extends TestCase
{
    protected function tearDown(): void
    {
        Context::clearAll();
    }

    public function testWithKeyAndKey()
    {
        $key = 'test-semaphore-key';
        $result = Context::withKey($key);
        $this->assertEquals($key, $result);
        $this->assertEquals($key, Context::key());

        $newKey = 'new-semaphore-key';
        Context::withKey($newKey);
        $this->assertEquals($newKey, Context::key());
    }

    public function testKeyDefaultValue()
    {
        $this->assertEquals('', Context::key());
    }

    public function testWithTokensAndTokens()
    {
        $tokens = 5;
        $result = Context::withTokens($tokens);
        $this->assertEquals($tokens, $result);
        $this->assertEquals($tokens, Context::tokens());

        $newTokens = 10;
        Context::withTokens($newTokens);
        $this->assertEquals($newTokens, Context::tokens());
    }

    public function testTokensDefaultValue()
    {
        $this->assertEquals(1, Context::tokens());
    }

    public function testTokensBoundaryValues()
    {
        Context::withTokens(0);
        $this->assertEquals(0, Context::tokens());

        Context::withTokens(-1);
        $this->assertEquals(-1, Context::tokens());

        Context::withTokens(1000000);
        $this->assertEquals(1000000, Context::tokens());
    }

    public function testWithAcquireAndAcquire()
    {
        $acquire = 3;
        $result = Context::withAcquire($acquire);
        $this->assertEquals($acquire, $result);
        $this->assertEquals($acquire, Context::acquire());

        $newAcquire = 7;
        Context::withAcquire($newAcquire);
        $this->assertEquals($newAcquire, Context::acquire());
    }

    public function testAcquireDefaultValue()
    {
        $this->assertEquals(1, Context::acquire());
    }

    public function testAcquireBoundaryValues()
    {
        Context::withAcquire(0);
        $this->assertEquals(0, Context::acquire());

        Context::withAcquire(-1);
        $this->assertEquals(-1, Context::acquire());

        Context::withAcquire(1000000);
        $this->assertEquals(1000000, Context::acquire());
    }

    public function testWithTimeoutAndTimeout()
    {
        $timeout = 5.5;
        $result = Context::withTimeout($timeout);
        $this->assertEquals($timeout, $result);
        $this->assertEquals($timeout, Context::timeout());

        $newTimeout = 10.8;
        Context::withTimeout($newTimeout);
        $this->assertEquals($newTimeout, Context::timeout());
    }

    public function testTimeoutDefaultValue()
    {
        $this->assertEquals(-1, Context::timeout());
    }

    public function testTimeoutBoundaryValues()
    {
        Context::withTimeout(0.0);
        $this->assertEquals(0.0, Context::timeout());

        Context::withTimeout(-5.5);
        $this->assertEquals(-5.5, Context::timeout());

        Context::withTimeout(0.001);
        $this->assertEquals(0.001, Context::timeout());

        Context::withTimeout(999999.999);
        $this->assertEquals(999999.999, Context::timeout());
    }

    public function testClear()
    {
        Context::withKey('test-key');
        Context::withTokens(5);
        Context::withAcquire(3);
        Context::withTimeout(10.5);

        Context::clear(Context::SEMAPHORE_KEY);
        $this->assertEquals('', Context::key());
        $this->assertEquals(5, Context::tokens());
        $this->assertEquals(3, Context::acquire());
        $this->assertEquals(10.5, Context::timeout());

        Context::clear(Context::SEMAPHORE_TOKENS);
        $this->assertEquals(1, Context::tokens());
        $this->assertEquals(3, Context::acquire());
        $this->assertEquals(10.5, Context::timeout());

        Context::clear(Context::SEMAPHORE_ACQUIRE);
        $this->assertEquals(1, Context::acquire());
        $this->assertEquals(10.5, Context::timeout());

        Context::clear(Context::SEMAPHORE_TIMEOUT);
        $this->assertEquals(-1, Context::timeout());
    }

    public function testClearAll()
    {
        Context::withKey('test-key');
        Context::withTokens(5);
        Context::withAcquire(3);
        Context::withTimeout(10.5);

        $this->assertEquals('test-key', Context::key());
        $this->assertEquals(5, Context::tokens());
        $this->assertEquals(3, Context::acquire());
        $this->assertEquals(10.5, Context::timeout());

        Context::clearAll();

        $this->assertEquals('', Context::key());
        $this->assertEquals(1, Context::tokens());
        $this->assertEquals(1, Context::acquire());
        $this->assertEquals(-1, Context::timeout());
    }

    public function testConstants()
    {
        $this->assertEquals('semaphore_key', Context::SEMAPHORE_KEY);
        $this->assertEquals('semaphore_tokens', Context::SEMAPHORE_TOKENS);
        $this->assertEquals('semaphore_acquire', Context::SEMAPHORE_ACQUIRE);
        $this->assertEquals('semaphore_timeout', Context::SEMAPHORE_TIMEOUT);
    }

    public function testMultipleOperations()
    {
        Context::withKey('initial-key');
        Context::withTokens(2);
        Context::withAcquire(1);
        Context::withTimeout(5.0);

        $this->assertEquals('initial-key', Context::key());
        $this->assertEquals(2, Context::tokens());
        $this->assertEquals(1, Context::acquire());
        $this->assertEquals(5.0, Context::timeout());

        Context::withKey('updated-key');
        Context::withTokens(10);

        $this->assertEquals('updated-key', Context::key());
        $this->assertEquals(10, Context::tokens());
        $this->assertEquals(1, Context::acquire());
        $this->assertEquals(5.0, Context::timeout());

        Context::clear(Context::SEMAPHORE_KEY);
        Context::clear(Context::SEMAPHORE_TIMEOUT);

        $this->assertEquals('', Context::key());
        $this->assertEquals(10, Context::tokens());
        $this->assertEquals(1, Context::acquire());
        $this->assertEquals(-1, Context::timeout());
    }

    public function testEdgeCases()
    {
        Context::withKey('');
        $this->assertEquals('', Context::key());

        $longKey = str_repeat('a', 1000);
        Context::withKey($longKey);
        $this->assertEquals($longKey, Context::key());

        $specialKey = 'key-with-!@#$%^&*()';
        Context::withKey($specialKey);
        $this->assertEquals($specialKey, Context::key());

        Context::withTimeout(0.0001);
        $this->assertEquals(0.0001, Context::timeout());
    }

    public function testIsolation()
    {
        Context::withTimeout(1.23);
        go(function () {
            $this->assertEmpty(HyperfContext::getContainer());
        });
    }
}
