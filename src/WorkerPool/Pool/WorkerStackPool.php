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

namespace Hyperf\Incubator\WorkerPool\Pool;

use Hyperf\Incubator\WorkerPool\Exception\RuntimeException;
use Hyperf\Incubator\WorkerPool\Worker;

class WorkerStackPool extends AbstractWorkerPool
{
    protected function insert(Worker $worker): void
    {
        $this->unshift($worker);
    }

    protected function detach(): ?Worker
    {
        return $this->shift();
    }

    private function unshift(Worker $worker): void
    {
        if ($this->len() >= $this->cap) {
            throw new RuntimeException("Pool capacity exceeded: {$this->cap}");
        }

        $this->heap->insert($worker);

        $node = $this->pushFront($worker);
        $worker->setNode($node);
    }

    private function shift(): ?Worker
    {
        $front = $this->front();
        if ($front === null) {
            return null;
        }

        $worker = $this->remove($front);
        $this->heap->remove($worker);

        return $worker;
    }
}
