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

use Closure;
use Hyperf\Engine\Channel;
use Hyperf\Incubator\WorkerPool\Exception\RuntimeException;
use Hyperf\Incubator\WorkerPool\Exception\TimeoutException;
use Hyperf\Incubator\WorkerPool\Heap\WorkerMinHeap;
use Hyperf\Incubator\WorkerPool\Pool\Contracts\WorkerPoolInterface;
use Hyperf\Incubator\WorkerPool\Worker;
use Iterator;
use WeakMap;

abstract class AbstractWorkerPool extends DoublyLinkedList implements WorkerPoolInterface
{
    protected WeakMap $refs;

    protected WorkerMinHeap $heap;

    protected Channel $requestChan;

    protected Closure $onDone;

    public function __construct(protected int $cap, protected bool $preSpawn, protected int $maxBlocks)
    {
        parent::__construct();

        $this->refs = new WeakMap();
        $this->heap = new WorkerMinHeap();

        $this->onDone = $this->release(...);
        $this->requestChan = new Channel();

        if ($this->preSpawn) {
            $this->spawnWorkers($this->cap);
        }
    }

    public function new(): Worker
    {
        $worker = new Worker($this->onDone);
        $this->refs[$worker] = true;

        return $worker->run();
    }

    public function get(float $timeout = 0): ?Worker
    {
        if ($worker = $this->detach()) {
            return $worker;
        }

        if ($this->len() == 0 && $this->cap > $this->refs->count()) {
            return $this->new();
        }

        if ($this->maxBlocks <= 0 || $this->requestChan->stats()['consumer_num'] >= $this->maxBlocks) {
            throw new RuntimeException('WorkerPool exhausted');
        }

        $worker = $this->requestChan->pop($timeout);
        if ($worker === false && $this->requestChan->isTimeout()) {
            throw new TimeoutException('Waiting for available worker timeout');
        }

        if ($worker === false && $this->requestChan->isClosing()) {
            throw new RuntimeException('WorkerPool closed');
        }

        return $worker;
    }

    public function release(Worker $worker): void
    {
        if ($this->requestChan->stats()['consumer_num'] > 0) {
            $this->requestChan->push($worker);
            return;
        }

        $this->insert($worker);
    }

    public function iterator(): Iterator
    {
        return $this->refs->getIterator();
    }

    public function collect(int $at): void
    {
        if ($this->heap->len() == 0) {
            return;
        }

        while (true) {
            $worker = $this->heap->top();
            if (is_null($worker)) {
                break;
            }
            if ($worker->activeAt() >= $at) {
                break;
            }
            $worker = $this->heap->extract();
            if (is_null($worker)) {
                break;
            }

            $this->del($worker);
            $worker->stop();
            unset($this->refs[$worker]);
        }
    }

    public function stop(): void
    {
        /**
         * @var Worker $worker
         */
        foreach ($this->iterator() as $worker => $v) {
            $worker->stop();
        }
    }

    abstract protected function insert(Worker $worker): void;

    abstract protected function detach(): ?Worker;

    protected function del(Worker $worker): void
    {
        if ($node = $worker->getNode()) {
            $this->remove($node);
        }
    }

    protected function spawnWorkers(int $num): void
    {
        for ($i = 0; $i < $num; ++$i) {
            $worker = $this->new();
            $this->insert($worker);
        }
    }
}
