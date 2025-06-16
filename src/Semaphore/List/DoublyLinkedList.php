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

namespace Hyperf\Incubator\Semaphore\List;

class DoublyLinkedList
{
    // sentinel node
    private ?Node $root;

    // length excluding the sentinel node
    private int $len = 0;

    public function __construct()
    {
        $this->root = new Node();
        $this->root->next = $this->root;
        $this->root->prev = $this->root;
    }

    public function init(): self
    {
        $this->root = new Node();
        $this->root->next = $this->root;
        $this->root->prev = $this->root;
        $this->len = 0;

        return $this;
    }

    public function len(): int
    {
        return $this->len;
    }

    public function front(): ?Node
    {
        if ($this->len == 0) {
            return null;
        }

        return $this->root->next;
    }

    public function back(): ?Node
    {
        if ($this->len == 0) {
            return null;
        }

        return $this->root->prev;
    }

    public function pushBack(mixed $value): Node
    {
        $node = new Node($value);
        $this->insertValue($node, $this->root->prev);

        return $node;
    }

    public function pushFront(mixed $value): Node
    {
        $node = new Node($value);
        $this->insertValue($node, $this->root);

        return $node;
    }

    public function insertAfter(mixed $value, Node $mark): ?Node
    {
        if ($mark->list !== $this) {
            return null;
        }

        $node = new Node($value);
        $this->insertValue($node, $mark);

        return $node;
    }

    public function insertBefore(mixed $value, Node $mark): ?Node
    {
        if ($mark->list !== $this) {
            return null;
        }

        $node = new Node($value);
        $this->insertValue($node, $mark->prev);

        return $node;
    }

    public function remove(Node $node): mixed
    {
        if ($node->list !== $this) {
            return null;
        }
        $this->removeNode($node);
        $node->list = null;
        $node->next = null;
        $node->prev = null;

        return $node->value();
    }

    public function moveToBack(Node $node): bool
    {
        if ($node->list !== $this || $node === $this->root->prev) {
            return false;
        }
        $this->moveNode($node, $this->root->prev);

        return true;
    }

    public function moveToFront(Node $node): bool
    {
        if ($node->list !== $this || $node === $this->root->next) {
            return false;
        }
        $this->moveNode($node, $this->root);

        return true;
    }

    public function moveAfter(Node $node, Node $mark): bool
    {
        if ($node->list !== $this || $node === $mark || $mark->list !== $this) {
            return false;
        }
        $this->moveNode($node, $mark);

        return true;
    }

    public function moveBefore(Node $node, Node $mark): bool
    {
        if ($node->list !== $this || $node === $mark || $mark->list !== $this) {
            return false;
        }
        $this->moveNode($node, $mark->prev);

        return true;
    }

    public function pushBackList(DoublyLinkedList $other): void
    {
        $i = $other->len;
        $node = $other->front();
        while ($i > 0) {
            $next = $node->next;
            $this->insertValue($node, $this->root->prev);
            $node = $next;
            --$i;
        }
        $other->init();
    }

    public function pushFrontList(DoublyLinkedList $other): void
    {
        $i = $other->len;
        $node = $other->back();
        while ($i > 0) {
            $prev = $node->prev;
            $this->insertValue($node, $this->root);
            $node = $prev;
            --$i;
        }
        $other->init();
    }

    private function insertValue(Node $node, Node $at): void
    {
        $n = $at->next;
        $at->next = $node;
        $node->prev = $at;
        $node->next = $n;
        $n->prev = $node;
        $node->list = $this;
        ++$this->len;
    }

    private function removeNode(Node $node): void
    {
        $node->prev->next = $node->next;
        $node->next->prev = $node->prev;
        --$this->len;
    }

    private function moveNode(Node $node, Node $at): void
    {
        if ($node === $at) {
            return;
        }
        $node->prev->next = $node->next;
        $node->next->prev = $node->prev;

        $n = $at->next;
        $at->next = $node;
        $node->prev = $at;
        $node->next = $n;
        $n->prev = $node;
    }
}
