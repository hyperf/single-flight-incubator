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

use Hyperf\Incubator\WorkerPool\Exception\RuntimeException;
use Hyperf\Incubator\WorkerPool\Worker;
use WeakMap;

class WorkerMinHeap extends AbstractHeap
{
    private WeakMap $workers;

    public function __construct()
    {
        $this->workers = new WeakMap();
    }

    public function less(int $i, int $j): bool
    {
        if (! $this->items[$i] instanceof Worker || ! $this->items[$j] instanceof Worker) {
            throw new RuntimeException('Only Worker type supported');
        }

        return $this->items[$i]->activeAt() < $this->items[$j]->activeAt();
    }

    public function insert(mixed $item): void
    {
        if (! $item instanceof Worker) {
            throw new RuntimeException('Only Worker type supported');
        }

        if (isset($this->workers[$item])) {
            $this->update($item);
            return;
        }

        $this->workers[$item] = true;
        parent::insert($item);
    }

    public function extract(): mixed
    {
        $worker = parent::extract();
        if ($worker !== null) {
            unset($this->workers[$worker]);
        }
        return $worker;
    }

    public function remove(mixed $item): mixed
    {
        if (! $item instanceof Worker) {
            throw new RuntimeException('Only Worker type supported');
        }

        if (! isset($this->workers[$item])) {
            return null;
        }

        $result = parent::remove($item);
        if ($result !== null) {
            unset($this->workers[$result]);
        }

        return $result;
    }

    public function contains(Worker $worker): bool
    {
        return isset($this->workers[$worker]);
    }

    public function update(Worker $worker): bool
    {
        if (! $this->contains($worker)) {
            return false;
        }

        $index = $this->findItem($worker);
        if ($index === false) {
            return false;
        }

        $this->fix($index);
        return true;
    }
}
