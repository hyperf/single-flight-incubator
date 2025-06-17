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

use Hyperf\Incubator\WorkerPool\Config;
use Hyperf\Incubator\WorkerPool\Exception\ConfigException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
#[CoversNothing]
class ConfigTest extends TestCase
{
    public function testDefaultValues()
    {
        $config = new Config();

        $this->assertEquals(10, $config->getCapacity());
        $this->assertEquals(Config::STACK_POOL, $config->getPoolType());
        $this->assertFalse($config->isPreSpawn());
        $this->assertEquals(-1, $config->getMaxBlocks());
        $this->assertEquals(-1, $config->getGcIntervalMs());
    }

    public function testSetCapacity()
    {
        $config = new Config();
        $config->setCapacity(100);

        $this->assertEquals(100, $config->getCapacity());
    }

    public function testSetPoolType()
    {
        $config = new Config();

        $config->setPoolType(Config::QUEUE_POOL);
        $this->assertEquals(Config::QUEUE_POOL, $config->getPoolType());

        $config->setPoolType(Config::STACK_POOL);
        $this->assertEquals(Config::STACK_POOL, $config->getPoolType());
    }

    public function testSetPreSpawn()
    {
        $config = new Config();
        $config->setPreSpawn(true);

        $this->assertTrue($config->isPreSpawn());
    }

    public function testSetMaxBlocks()
    {
        $config = new Config();
        $config->setMaxBlocks(500);

        $this->assertEquals(500, $config->getMaxBlocks());
    }

    public function testSetCollectInactiveWorker()
    {
        $config = new Config();
        $config->setCollectInactiveWorker(1000);

        $this->assertEquals(1000, $config->getGcIntervalMs());
    }

    public function testCheckInvalidPoolType()
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid pool type, only "stack" or "queue" supported');

        $config = new Config();
        $config->setPoolType('invalid');
        $config->check();
    }

    public function testCheckCapacityExceedsLimit()
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Capacity exceeds maximum limit of ' . Config::MAX_CAPACITY);

        $config = new Config();
        $config->setCapacity(Config::MAX_CAPACITY + 1);
        $config->check();
    }

    public function testCheckMaxBlocksExceedsLimit()
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Maximum blocks exceeds limit of ' . Config::MAX_BLOCKS);

        $config = new Config();
        $config->setMaxBlocks(Config::MAX_BLOCKS + 1);
        $config->check();
    }

    public function testFluentInterface()
    {
        $config = new Config();

        $result = $config->setCapacity(50)
            ->setPoolType(Config::QUEUE_POOL)
            ->setPreSpawn(true)
            ->setMaxBlocks(100);

        $this->assertSame($config, $result);
        $this->assertEquals(50, $config->getCapacity());
        $this->assertEquals(Config::QUEUE_POOL, $config->getPoolType());
        $this->assertTrue($config->isPreSpawn());
        $this->assertEquals(100, $config->getMaxBlocks());
    }
}
