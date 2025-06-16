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

use Hyperf\Incubator\Semaphore\List\Queue;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class QueueTest extends TestCase
{
    public function testInitialization()
    {
        $queue = new Queue();
        $this->assertTrue($queue->empty());
        $this->assertNull($queue->peek());
    }

    public function testEnqueueAndPeek()
    {
        $queue = new Queue();
        $queue->enqueue('value1');

        $this->assertFalse($queue->empty());
        $this->assertEquals(1, $queue->len());
        $this->assertEquals('value1', $queue->peek()->value());

        $queue->enqueue('value2');
        $this->assertEquals(2, $queue->len());
        $this->assertEquals('value1', $queue->peek()->value());
    }

    public function testDequeue()
    {
        $queue = new Queue();
        $queue->enqueue('value1');
        $queue->enqueue('value2');
        $queue->enqueue('value3');

        $this->assertEquals(3, $queue->len());

        $value1 = $queue->dequeue();
        $this->assertEquals('value1', $value1);
        $this->assertEquals(2, $queue->len());
        $this->assertEquals('value2', $queue->peek()->value());

        $value2 = $queue->dequeue();
        $this->assertEquals('value2', $value2);
        $this->assertEquals(1, $queue->len());
        $this->assertEquals('value3', $queue->peek()->value());

        $value3 = $queue->dequeue();
        $this->assertEquals('value3', $value3);
        $this->assertEquals(0, $queue->len());
        $this->assertTrue($queue->empty());
        $this->assertNull($queue->peek());
    }

    public function testDequeueEmptyQueue()
    {
        $queue = new Queue();
        $this->assertNull($queue->dequeue());
        $this->assertTrue($queue->empty());
    }

    public function testMultipleOperations()
    {
        $queue = new Queue();
        $queue->enqueue('value1');
        $queue->enqueue('value2');

        $this->assertEquals('value1', $queue->dequeue());

        $queue->enqueue('value3');
        $queue->enqueue('value4');

        $this->assertEquals('value2', $queue->dequeue());
        $this->assertEquals('value3', $queue->dequeue());

        $queue->enqueue('value5');

        $this->assertEquals('value4', $queue->dequeue());
        $this->assertEquals('value5', $queue->dequeue());

        $this->assertTrue($queue->empty());
    }
}
