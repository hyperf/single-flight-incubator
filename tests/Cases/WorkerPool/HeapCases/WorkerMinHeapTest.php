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

namespace Hyperf\Incubator\Cases\WorkerPool\HeapCases;

use Hyperf\Incubator\WorkerPool\Heap\WorkerMinHeap;
use Hyperf\Incubator\WorkerPool\Worker;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class WorkerMinHeapTest extends TestCase
{
    public function testPushAndPop()
    {
        $heap = new WorkerMinHeap();

        $this->assertEquals(0, $heap->len());

        $worker1 = new Worker();
        $worker1->updateActiveAt(1);

        $worker2 = new Worker();
        $worker2->updateActiveAt(2);

        $worker3 = new Worker();
        $worker3->updateActiveAt(3);

        $worker4 = new Worker();
        $worker4->updateActiveAt(4);

        $heap->insert($worker1);
        $heap->insert($worker2);
        $heap->insert($worker3);
        $heap->insert($worker4);

        $this->assertEquals(4, $heap->len());

        $this->assertSame($worker1, $heap->extract());
        $this->assertSame($worker2, $heap->extract());
        $this->assertSame($worker3, $heap->extract());
        $this->assertSame($worker4, $heap->extract());

        $this->assertEquals(0, $heap->len());
    }

    public function testRemove()
    {
        $heap = new WorkerMinHeap();

        $worker1 = new Worker();
        $worker1->updateActiveAt(1);

        $worker2 = new Worker();
        $worker2->updateActiveAt(2);

        $worker3 = new Worker();
        $worker3->updateActiveAt(3);

        $worker4 = new Worker();
        $worker4->updateActiveAt(4);

        $worker5 = new Worker();
        $worker5->updateActiveAt(5);

        $heap->insert($worker5);
        $heap->insert($worker3);
        $heap->insert($worker1);
        $heap->insert($worker2);
        $heap->insert($worker4);

        $this->assertEquals(5, $heap->len());

        $removed = $heap->remove($worker3);
        $this->assertSame($worker3, $removed);

        $this->assertEquals(4, $heap->len());

        $this->assertSame($worker1, $heap->extract());
        $this->assertSame($worker2, $heap->extract());
        $this->assertSame($worker4, $heap->extract());
        $this->assertSame($worker5, $heap->extract());
    }

    public function testUpdate()
    {
        $heap = new WorkerMinHeap();

        $worker1 = new Worker();
        $worker1->updateActiveAt(10);

        $worker2 = new Worker();
        $worker2->updateActiveAt(20);

        $worker3 = new Worker();
        $worker3->updateActiveAt(30);

        $heap->insert($worker1);
        $heap->insert($worker2);
        $heap->insert($worker3);

        $this->assertEquals(3, $heap->len());

        $worker3->updateActiveAt(5);
        $heap->update($worker3);

        $this->assertSame($worker3, $heap->extract());
        $this->assertSame($worker1, $heap->extract());
        $this->assertSame($worker2, $heap->extract());
    }

    public function testContains()
    {
        $heap = new WorkerMinHeap();

        $worker1 = new Worker();
        $worker1->updateActiveAt(1);

        $worker2 = new Worker();
        $worker2->updateActiveAt(2);

        $worker3 = new Worker();
        $worker3->updateActiveAt(3);

        $heap->insert($worker1);
        $heap->insert($worker2);

        $this->assertTrue($heap->contains($worker1));
        $this->assertTrue($heap->contains($worker2));
        $this->assertFalse($heap->contains($worker3));

        $heap->extract();
        $this->assertFalse($heap->contains($worker1));
        $this->assertTrue($heap->contains($worker2));
    }

    public function testDuplicateInsert()
    {
        $heap = new WorkerMinHeap();

        $worker1 = new Worker();
        $worker1->updateActiveAt(1);

        $heap->insert($worker1);
        $this->assertEquals(1, $heap->len());

        $heap->insert($worker1);
        $this->assertEquals(1, $heap->len());
    }

    public function testHeapUpdate()
    {
        $heap = new WorkerMinHeap();

        $worker1 = new Worker();
        $worker1->updateActiveAt(10);

        $worker2 = new Worker();
        $worker2->updateActiveAt(20);

        $worker3 = new Worker();
        $worker3->updateActiveAt(30);

        $heap->insert($worker1);
        $heap->insert($worker2);
        $heap->insert($worker3);

        $this->assertEquals(3, $heap->len());

        $worker2->updateActiveAt(5);
        $heap->update($worker2);

        $this->assertSame($worker2, $heap->extract());
        $this->assertSame($worker1, $heap->extract());
        $this->assertSame($worker3, $heap->extract());

        $this->assertEquals(0, $heap->len());
    }
}
