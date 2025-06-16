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

class Queue extends DoublyLinkedList
{
    public function enqueue(mixed $value): Node
    {
        return $this->pushBack($value);
    }

    public function dequeue(): mixed
    {
        $front = $this->front();
        if ($front === null) {
            return null;
        }

        return $this->remove($front);
    }

    public function peek(): ?Node
    {
        return $this->front();
    }

    public function empty(): bool
    {
        return $this->len() === 0;
    }
}
