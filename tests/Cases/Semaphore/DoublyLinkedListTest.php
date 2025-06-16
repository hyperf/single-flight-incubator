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

use Hyperf\Incubator\Semaphore\List\DoublyLinkedList;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class DoublyLinkedListTest extends TestCase
{
    public function testInitialization()
    {
        $list = new DoublyLinkedList();
        $this->assertEquals(0, $list->len());
        $this->assertNull($list->front());
        $this->assertNull($list->back());
    }

    public function testInit()
    {
        $list = new DoublyLinkedList();
        $list->pushBack('value1');
        $list->pushBack('value2');

        $list->init();

        $this->assertEquals(0, $list->len());
        $this->assertNull($list->front());
        $this->assertNull($list->back());
    }

    public function testPushBack()
    {
        $list = new DoublyLinkedList();
        $list->pushBack('value1');
        $list->pushBack('value2');
        $list->pushBack('value3');

        $this->assertEquals(3, $list->len());
        $this->assertEquals('value1', $list->front()->value());
        $this->assertEquals('value3', $list->back()->value());
    }

    public function testPushFront()
    {
        $list = new DoublyLinkedList();
        $list->pushFront('value1');
        $list->pushFront('value2');
        $list->pushFront('value3');

        $this->assertEquals(3, $list->len());
        $this->assertEquals('value3', $list->front()->value());
        $this->assertEquals('value1', $list->back()->value());
    }

    public function testInsertAfter()
    {
        $list = new DoublyLinkedList();
        $node1 = $list->pushBack('value1');
        $list->pushBack('value3');

        $list->insertAfter('value2', $node1);

        $this->assertEquals(3, $list->len());
        $this->assertEquals('value1', $list->front()->value());
        $this->assertEquals('value2', $list->front()->next->value());
        $this->assertEquals('value3', $list->back()->value());
    }

    public function testInsertBefore()
    {
        $list = new DoublyLinkedList();
        $list->pushBack('value1');
        $node3 = $list->pushBack('value3');

        $list->insertBefore('value2', $node3);

        $this->assertEquals(3, $list->len());
        $this->assertEquals('value1', $list->front()->value());
        $this->assertEquals('value2', $list->front()->next->value());
        $this->assertEquals('value3', $list->back()->value());
    }

    public function testRemove()
    {
        $list = new DoublyLinkedList();
        $list->pushBack('value1');
        $node2 = $list->pushBack('value2');
        $list->pushBack('value3');

        $removedValue = $list->remove($node2);

        $this->assertEquals('value2', $removedValue);
        $this->assertEquals(2, $list->len());
        $this->assertEquals('value1', $list->front()->value());
        $this->assertEquals('value3', $list->back()->value());
        $this->assertEquals('value3', $list->front()->next->value());
    }

    public function testMoveToBack()
    {
        $list = new DoublyLinkedList();
        $node1 = $list->pushBack('value1');
        $list->pushBack('value2');
        $list->pushBack('value3');

        $result = $list->moveToBack($node1);

        $this->assertTrue($result);
        $this->assertEquals(3, $list->len());
        $this->assertEquals('value2', $list->front()->value());
        $this->assertEquals('value1', $list->back()->value());
    }

    public function testMoveToFront()
    {
        $list = new DoublyLinkedList();
        $list->pushBack('value1');
        $list->pushBack('value2');
        $node3 = $list->pushBack('value3');

        $result = $list->moveToFront($node3);

        $this->assertTrue($result);
        $this->assertEquals(3, $list->len());
        $this->assertEquals('value3', $list->front()->value());
        $this->assertEquals('value2', $list->back()->value());
    }

    public function testMoveAfter()
    {
        $list = new DoublyLinkedList();
        $node1 = $list->pushBack('value1');
        $list->pushBack('value2');
        $node3 = $list->pushBack('value3');

        $result = $list->moveAfter($node1, $node3);

        $this->assertTrue($result);
        $this->assertEquals(3, $list->len());
        $this->assertEquals('value2', $list->front()->value());
        $this->assertEquals('value1', $list->back()->value());
    }

    public function testMoveBefore()
    {
        $list = new DoublyLinkedList();
        $node1 = $list->pushBack('value1');
        $list->pushBack('value2');
        $node3 = $list->pushBack('value3');

        $result = $list->moveBefore($node3, $node1);

        $this->assertTrue($result);
        $this->assertEquals(3, $list->len());
        $this->assertEquals('value3', $list->front()->value());
        $this->assertEquals('value2', $list->back()->value());
    }

    public function testPushBackList()
    {
        $list1 = new DoublyLinkedList();
        $list1->pushBack('value1');
        $list1->pushBack('value2');

        $list2 = new DoublyLinkedList();
        $list2->pushBack('value3');
        $list2->pushBack('value4');

        $list1->pushBackList($list2);

        $this->assertEquals(4, $list1->len());
        $this->assertEquals(0, $list2->len());
        $this->assertEquals('value1', $list1->front()->value());
        $this->assertEquals('value4', $list1->back()->value());
    }

    public function testPushFrontList()
    {
        $list1 = new DoublyLinkedList();
        $list1->pushBack('value1');
        $list1->pushBack('value2');

        $list2 = new DoublyLinkedList();
        $list2->pushBack('value3');
        $list2->pushBack('value4');

        $list1->pushFrontList($list2);

        $this->assertEquals(4, $list1->len());
        $this->assertEquals(0, $list2->len());
        $this->assertEquals('value3', $list1->front()->value());
        $this->assertEquals('value2', $list1->back()->value());
    }
}
