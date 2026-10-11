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
use Hyperf\Incubator\WorkerPool\Pool\Contracts\WorkerPoolInterface;
use Hyperf\Incubator\WorkerPool\Worker;
use Iterator;
use SplDoublyLinkedList;
use WeakMap;

abstract class AbstractWorkerPool implements WorkerPoolInterface
{
    /** @var WeakMap<Worker, true> */
    protected WeakMap $refs;

    /**
     * @var SplDoublyLinkedList<Worker>
     *
     * Idle workers ordered by activeAt: the timestamps are taken in the same
     * un-interrupted stretch that pushes the worker, so the least recently
     * active one always stays at the front, and inactive workers always pile
     * up at the front and are removed end by end
     */
    protected SplDoublyLinkedList $idle;

    /** @var Channel<Worker> */
    protected Channel $requestChan;

    protected Closure $onDone;

    public function __construct(protected int $cap, protected bool $preSpawn, protected int $maxBlocks)
    {
        $this->refs = new WeakMap();
        $this->idle = $this->newIdleList();

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

        if ($this->idle->isEmpty() && $this->cap > $this->refs->count()) {
            return $this->new();
        }

        if ($this->maxBlocks <= 0 || $this->requestChan->stats()['consumer_num'] >= $this->maxBlocks) {
            throw new RuntimeException('WorkerPool exhausted');
        }

        $worker = $this->requestChan->pop($timeout);
        if ($worker === false) {
            if ($this->requestChan->isTimeout()) {
                throw new TimeoutException('Waiting for available worker timeout');
            }
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

    /**
     * @return Iterator<Worker, true>
     */
    public function iterator(): Iterator
    {
        $iterator = $this->refs->getIterator();
        \assert($iterator instanceof Iterator);

        return $iterator;
    }

    public function collect(int $at): void
    {
        while (! $this->idle->isEmpty() && $this->idle->bottom()->activeAt() < $at) {
            $worker = $this->idle->shift();
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

    protected function insert(Worker $worker): void
    {
        if ($this->idle->count() >= $this->cap) {
            throw new RuntimeException("Pool capacity exceeded: {$this->cap}");
        }

        $this->idle->push($worker);
    }

    /**
     * @return SplDoublyLinkedList<Worker>
     */
    abstract protected function newIdleList(): SplDoublyLinkedList;

    abstract protected function detach(): ?Worker;

    protected function spawnWorkers(int $num): void
    {
        for ($i = 0; $i < $num; ++$i) {
            $worker = $this->new();
            $this->insert($worker);
        }
    }
}
