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

namespace HyperfTest\Incubator\Stubs\WorkerPool;

use Hyperf\Incubator\WorkerPool\Heap\AbstractHeap;

class IntMinHeap extends AbstractHeap
{
    public function less(int $i, int $j): bool
    {
        return $this->items[$i] < $this->items[$j];
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function setItems(array $items, bool $rebuild = true): void
    {
        $this->items = $items;
        if ($rebuild) {
            $this->build();
        }
    }

    protected function findItem(mixed $element): false|int
    {
        foreach ($this->items as $index => $value) {
            if ($value === $element) {
                return $index;
            }
        }

        return false;
    }
}
