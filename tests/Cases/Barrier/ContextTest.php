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

use Hyperf\Context\Context as HyperfContext;
use Hyperf\Incubator\Barrier\Context;
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
        $key = 'test_barrier_key';
        $result = Context::withKey($key);
        $this->assertEquals($key, $result);
        $this->assertEquals($key, Context::key());

        Context::withKey('');
        $this->assertEquals('', Context::key());

        Context::clearAll();
        $this->assertEquals('', Context::key());
    }

    public function testWithPartiesAndParties()
    {
        $parties = 5;
        $result = Context::withParties($parties);
        $this->assertEquals($parties, $result);
        $this->assertEquals($parties, Context::parties());

        Context::withParties(0);
        $this->assertEquals(0, Context::parties());

        Context::withParties(-1);
        $this->assertEquals(-1, Context::parties());

        Context::clearAll();
        $this->assertEquals(0, Context::parties());
    }

    public function testWithTimeoutAndTimeout()
    {
        $timeout = 10.5;
        $result = Context::withTimeout($timeout);
        $this->assertEquals($timeout, $result);
        $this->assertEquals($timeout, Context::timeout());

        Context::withTimeout(0.0);
        $this->assertEquals(0.0, Context::timeout());

        Context::withTimeout(-1.0);
        $this->assertEquals(-1.0, Context::timeout());

        Context::clearAll();
        $this->assertEquals(-1, Context::timeout());
    }

    public function testClear()
    {
        Context::withKey('test_key');
        Context::withParties(3);
        Context::withTimeout(5.0);

        Context::clear(Context::BARRIER_KEY);
        $this->assertEquals('', Context::key());
        $this->assertEquals(3, Context::parties());
        $this->assertEquals(5.0, Context::timeout());

        Context::clear(Context::BARRIER_PARTIES);
        $this->assertEquals('', Context::key());
        $this->assertEquals(0, Context::parties());
        $this->assertEquals(5.0, Context::timeout());

        Context::clear(Context::BARRIER_TIMEOUT);
        $this->assertEquals('', Context::key());
        $this->assertEquals(0, Context::parties());
        $this->assertEquals(-1, Context::timeout());
    }

    public function testClearAll()
    {
        Context::withKey('test_key');
        Context::withParties(3);
        Context::withTimeout(5.0);

        $this->assertEquals('test_key', Context::key());
        $this->assertEquals(3, Context::parties());
        $this->assertEquals(5.0, Context::timeout());

        Context::clearAll();

        $this->assertEquals('', Context::key());
        $this->assertEquals(0, Context::parties());
        $this->assertEquals(-1, Context::timeout());
    }

    public function testConstants()
    {
        $this->assertEquals('barrier_key', Context::BARRIER_KEY);
        $this->assertEquals('barrier_parties', Context::BARRIER_PARTIES);
        $this->assertEquals('barrier_timeout', Context::BARRIER_TIMEOUT);
    }

    public function testMultipleOperations()
    {
        Context::withKey('key1');
        $this->assertEquals('key1', Context::key());

        Context::withKey('key2');
        $this->assertEquals('key2', Context::key());

        Context::withParties(10);
        $this->assertEquals(10, Context::parties());
        $this->assertEquals('key2', Context::key());

        Context::withTimeout(15.5);
        $this->assertEquals(15.5, Context::timeout());
        $this->assertEquals(10, Context::parties());
        $this->assertEquals('key2', Context::key());
    }

    public function testEdgeCases()
    {
        Context::withParties(PHP_INT_MAX);
        $this->assertEquals(PHP_INT_MAX, Context::parties());

        Context::withParties(PHP_INT_MIN);
        $this->assertEquals(PHP_INT_MIN, Context::parties());

        Context::withTimeout(PHP_FLOAT_MAX);
        $this->assertEquals(PHP_FLOAT_MAX, Context::timeout());

        Context::withTimeout(-PHP_FLOAT_MAX);
        $this->assertEquals(-PHP_FLOAT_MAX, Context::timeout());

        $longKey = str_repeat('a', 1000);
        Context::withKey($longKey);
        $this->assertEquals($longKey, Context::key());
    }

    public function testIsolation()
    {
        Context::withTimeout(1.23);
        go(function () {
            $this->assertEmpty(HyperfContext::getContainer());
        });
    }
}
