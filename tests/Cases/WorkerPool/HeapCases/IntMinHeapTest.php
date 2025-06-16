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

use HyperfTest\Incubator\Stubs\WorkerPool\IntMinHeap;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class IntMinHeapTest extends TestCase
{
    public function testPushAndPop()
    {
        $heap = new IntMinHeap();

        $this->assertEquals(0, $heap->len());

        $heap->insert(3);
        $heap->insert(1);
        $heap->insert(4);
        $heap->insert(2);

        $this->assertEquals(4, $heap->len());

        $this->assertEquals(1, $heap->extract());
        $this->assertEquals(2, $heap->extract());
        $this->assertEquals(3, $heap->extract());
        $this->assertEquals(4, $heap->extract());

        $this->assertEquals(0, $heap->len());
    }

    public function testSetItems()
    {
        $heap = new IntMinHeap();

        $heap->setItems([5, 2, 7, 1, 3]);

        $this->assertEquals(5, $heap->len());

        $this->assertEquals(1, $heap->extract());
        $this->assertEquals(2, $heap->extract());
        $this->assertEquals(3, $heap->extract());
        $this->assertEquals(5, $heap->extract());
        $this->assertEquals(7, $heap->extract());
    }

    public function testRemove()
    {
        $heap = new IntMinHeap();

        $heap->insert(5);
        $heap->insert(3);
        $heap->insert(7);
        $heap->insert(1);
        $heap->insert(9);

        $this->assertEquals(5, $heap->len());

        $removed = $heap->remove(7);
        $this->assertEquals(7, $removed);

        $this->assertEquals(4, $heap->len());

        $this->assertEquals(1, $heap->extract());
        $this->assertEquals(3, $heap->extract());
        $this->assertEquals(5, $heap->extract());
        $this->assertEquals(9, $heap->extract());
    }

    public function testRebuild()
    {
        $heap = new IntMinHeap();

        $heap->insert(5);
        $heap->insert(3);
        $heap->insert(7);
        $heap->insert(1);
        $heap->insert(9);

        $items = $heap->getItems();

        $index = array_search(7, $items);

        $items[$index] = 0;
        $heap->setItems($items);

        $this->assertEquals(0, $heap->extract());
        $this->assertEquals(1, $heap->extract());
        $this->assertEquals(3, $heap->extract());
        $this->assertEquals(5, $heap->extract());
        $this->assertEquals(9, $heap->extract());
    }

    public function testFix()
    {
        $heap = new IntMinHeap();

        $heap->insert(5);
        $heap->insert(3);
        $heap->insert(7);
        $heap->insert(1);
        $heap->insert(9);

        $items = $heap->getItems();

        $index = array_search(7, $items);
        $this->assertNotFalse($index);

        $items[$index] = 0;
        $heap->setItems($items, false);

        $heap->fix($index);

        $this->assertEquals(0, $heap->extract());
        $this->assertEquals(1, $heap->extract());
        $this->assertEquals(3, $heap->extract());
        $this->assertEquals(5, $heap->extract());
        $this->assertEquals(9, $heap->extract());
    }
}
