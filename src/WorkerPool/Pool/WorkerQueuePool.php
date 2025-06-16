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

class WorkerQueuePool extends AbstractWorkerPool
{
    protected function insert(Worker $worker): void
    {
        $this->enqueue($worker);
    }

    protected function detach(): ?Worker
    {
        return $this->dequeue();
    }

    private function enqueue(Worker $worker): void
    {
        if ($this->len() >= $this->cap) {
            throw new RuntimeException("Pool capacity exceeded: {$this->cap}");
        }

        $this->heap->insert($worker);

        $node = $this->pushBack($worker);
        $worker->setNode($node);
    }

    private function dequeue(): ?Worker
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
