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

namespace Hyperf\Incubator\WorkerPool\Heap;

abstract class AbstractHeap implements HeapInterface
{
    protected array $items = [];

    public function len(): int
    {
        return count($this->items);
    }

    public function insert(mixed $item): void
    {
        $this->items[] = $item;
        $this->up();
    }

    public function top(): mixed
    {
        if (count($this->items) == 0) {
            return null;
        }

        return $this->items[0];
    }

    public function extract(): mixed
    {
        if ($this->len() === 0) {
            return null;
        }

        $n = $this->len() - 1;
        $this->swap(0, $n);
        $result = $this->items[$n];
        unset($this->items[$n]);
        $this->items = array_values($this->items);

        if ($this->len() > 0) {
            $this->down(0, $this->len());
        }

        return $result;
    }

    public function remove(mixed $item): mixed
    {
        $index = $this->findItem($item);
        if ($index === false) {
            return null;
        }

        $n = $this->len() - 1;
        if ($index === $n) {
            $result = $this->items[$index];
            unset($this->items[$index]);
            $this->items = array_values($this->items);
            return $result;
        }

        $this->swap($index, $n);
        $result = $this->items[$n];
        unset($this->items[$n]);
        $this->items = array_values($this->items);

        if ($this->len() > 0 && $index < $this->len()) {
            if (! $this->down($index, $this->len())) {
                $this->up($index);
            }
        }

        return $result;
    }

    public function fix(int $i): void
    {
        if ($i < 0 || $i >= $this->len()) {
            return;
        }

        if (! $this->down($i, $this->len())) {
            $this->up($i);
        }
    }

    public function build(): void
    {
        $n = $this->len();
        for ($i = intdiv($n, 2) - 1; $i >= 0; --$i) {
            $this->down($i, $n);
        }
    }

    public function swap(int $i, int $j): void
    {
        if ($i !== $j) {
            $temp = $this->items[$i];
            $this->items[$i] = $this->items[$j];
            $this->items[$j] = $temp;
        }
    }

    abstract public function less(int $i, int $j): bool;

    protected function up(?int $j = null): void
    {
        if ($j === null) {
            $j = $this->len() - 1;
        }

        while (true) {
            $i = intdiv($j - 1, 2);
            if ($i === $j || ! $this->less($j, $i)) {
                break;
            }
            $this->swap($i, $j);
            $j = $i;
        }
    }

    protected function down(int $i0, int $n): bool
    {
        $i = $i0;
        while (true) {
            $j1 = 2 * $i + 1;
            if ($j1 >= $n || $j1 < 0) {
                break;
            }
            $j = $j1;
            $j2 = $j1 + 1;
            if ($j2 < $n && $this->less($j2, $j1)) {
                $j = $j2;
            }
            if (! $this->less($j, $i)) {
                break;
            }
            $this->swap($i, $j);
            $i = $j;
        }
        return $i > $i0;
    }

    protected function findItem(mixed $element): false|int
    {
        return array_search($element, $this->items, true);
    }
}
