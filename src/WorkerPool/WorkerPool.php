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

namespace Hyperf\Incubator\WorkerPool;

use Hyperf\Engine\Channel;
use Hyperf\Incubator\WorkerPool\Exception\RuntimeException;
use Hyperf\Incubator\WorkerPool\Exception\WorkerPoolException;
use Hyperf\Incubator\WorkerPool\Pool\Contracts\WorkerPoolInterface;
use Hyperf\Incubator\WorkerPool\Pool\WorkerQueuePool;
use Hyperf\Incubator\WorkerPool\Pool\WorkerStackPool;

use function Hyperf\Coroutine\go;

class WorkerPool
{
    private bool $running = true;

    private ?Channel $gcChan = null;

    private ?WorkerPoolInterface $workers;

    public function __construct(protected ?Config $config = null)
    {
        if ($this->config === null) {
            $this->config = new Config();
        }
        $this->config->check();

        $this->workers = match ($this->config->getPoolType()) {
            Config::QUEUE_POOL => new WorkerQueuePool($this->config->getCapacity(), $this->config->isPreSpawn(), $this->config->getMaxBlocks()),
            Config::STACK_POOL => new WorkerStackPool($this->config->getCapacity(), $this->config->isPreSpawn(), $this->config->getMaxBlocks()),
        };

        $this->collectWorkers();
    }

    /**
     * @throws WorkerPoolException
     */
    public function submit(callable $task, float $timeout = -1, bool $sync = false): mixed
    {
        return $this->submitTask(new Task($task(...), $sync), $timeout);
    }

    /**
     * @throws WorkerPoolException
     */
    public function submitTask(TaskInterface $task, float $timeout = -1): mixed
    {
        if (! $this->running) {
            throw new RuntimeException('WorkerPool closed, cannot submit task');
        }

        $worker = $this->workers->get($timeout);

        $ret = $worker->submit($task);
        if ($ret instanceof WorkerPoolException) {
            throw $ret;
        }

        return $ret;
    }

    public function stop(): void
    {
        $this->running = false;
        $this->gcChan?->close();
        $this->workers->stop();
    }

    private function collectWorkers(): void
    {
        if ($this->config->getGcIntervalMs() != -1) {
            $interval = $this->config->getGcIntervalMs();
            $this->gcChan = new Channel();
            go(function () use ($interval) {
                $intervalSecond = $interval / 1000.0;
                while (true) {
                    if (! $this->running) {
                        break;
                    }
                    $this->gcChan->pop($intervalSecond);
                    if (! $this->running) {
                        break;
                    }
                    if ($this->gcChan->isTimeout()) {
                        $at = (int) (microtime(true) * 1000) - $interval;
                        $this->workers->collect($at);
                        continue;
                    }
                    break;
                }
            });
        }
    }
}
