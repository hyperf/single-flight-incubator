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

use Hyperf\Context\Context as HyperfContext;
use Hyperf\Incubator\WorkerPool\Context;
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

    public function testWithNameAndName()
    {
        $name = 'test-worker-pool';
        $result = Context::withName($name);
        $this->assertEquals($name, $result);
        $this->assertEquals($name, Context::name());

        $newName = 'new-worker-pool';
        Context::withName($newName);
        $this->assertEquals($newName, Context::name());
    }

    public function testNameDefaultValue()
    {
        $this->assertEquals('', Context::name());
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

    public function testWithSyncAndSync()
    {
        $sync = false;
        $result = Context::withSync($sync);
        $this->assertEquals($sync, $result);
        $this->assertEquals($sync, Context::sync());

        $newSync = true;
        Context::withSync($newSync);
        $this->assertEquals($newSync, Context::sync());
    }

    public function testSyncDefaultValue()
    {
        $this->assertEquals(true, Context::sync());
    }

    public function testClear()
    {
        Context::withName('test-name');
        Context::withTimeout(10.5);
        Context::withSync(false);

        Context::clear(Context::WORKERPOOL_NAME);
        $this->assertEquals('', Context::name());
        $this->assertEquals(10.5, Context::timeout());
        $this->assertEquals(false, Context::sync());

        Context::clear(Context::WORKERPOOL_TIMEOUT);
        $this->assertEquals(-1, Context::timeout());
        $this->assertEquals(false, Context::sync());

        Context::clear(Context::WORKERPOOL_SYNC);
        $this->assertEquals(true, Context::sync());
    }

    public function testClearAll()
    {
        Context::withName('test-name');
        Context::withTimeout(10.5);
        Context::withSync(false);

        $this->assertEquals('test-name', Context::name());
        $this->assertEquals(10.5, Context::timeout());
        $this->assertEquals(false, Context::sync());

        Context::clearAll();

        $this->assertEquals('', Context::name());
        $this->assertEquals(-1, Context::timeout());
        $this->assertEquals(true, Context::sync());
    }

    public function testConstants()
    {
        $this->assertEquals('worker_pool_name', Context::WORKERPOOL_NAME);
        $this->assertEquals('worker_pool_timeout', Context::WORKERPOOL_TIMEOUT);
        $this->assertEquals('worker_pool_sync', Context::WORKERPOOL_SYNC);
    }

    public function testMultipleOperations()
    {
        Context::withName('initial-name');
        Context::withTimeout(5.0);
        Context::withSync(true);

        $this->assertEquals('initial-name', Context::name());
        $this->assertEquals(5.0, Context::timeout());
        $this->assertEquals(true, Context::sync());

        Context::withName('updated-name');
        Context::withSync(false);

        $this->assertEquals('updated-name', Context::name());
        $this->assertEquals(5.0, Context::timeout());
        $this->assertEquals(false, Context::sync());

        Context::clear(Context::WORKERPOOL_NAME);
        Context::clear(Context::WORKERPOOL_TIMEOUT);

        $this->assertEquals('', Context::name());
        $this->assertEquals(-1, Context::timeout());
        $this->assertEquals(false, Context::sync());
    }

    public function testEdgeCases()
    {
        Context::withName('');
        $this->assertEquals('', Context::name());

        $longName = str_repeat('a', 1000);
        Context::withName($longName);
        $this->assertEquals($longName, Context::name());

        $specialName = 'name-with-!@#$%^&*()';
        Context::withName($specialName);
        $this->assertEquals($specialName, Context::name());

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
