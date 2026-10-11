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

use Hyperf\Incubator\WorkerPool\Worker;
use SplDoublyLinkedList;
use SplStack;

class WorkerStackPool extends AbstractWorkerPool
{
    /**
     * @return SplDoublyLinkedList<Worker>
     */
    protected function newIdleList(): SplDoublyLinkedList
    {
        return new SplStack();
    }

    protected function detach(): ?Worker
    {
        if ($this->idle->isEmpty()) {
            return null;
        }

        return $this->idle->pop();
    }
}
