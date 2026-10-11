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

use Closure;
use Hyperf\Engine\Channel;
use Hyperf\Incubator\WorkerPool\Exception\RuntimeException;
use Throwable;

use function Hyperf\Coroutine\go;

class Worker
{
    protected bool $running;

    protected int $activeAt;

    /** @var Channel<TaskInterface> */
    protected Channel $channel;

    public function __construct(protected ?Closure $onDone = null)
    {
        $this->channel = new Channel();

        $this->updateActiveAt();
    }

    public function submit(TaskInterface $task): mixed
    {
        if (! $this->running) {
            throw new RuntimeException('Worker already stopped');
        }

        $this->channel->push($task);
        if ($task->isSync()) {
            return $task->waitResult();
        }

        return null;
    }

    public function run(): self
    {
        $this->running = true;
        go(function () {
            while (true) {
                $task = $this->channel->pop();
                if ($task === false) {
                    // the worker was stopped
                    break;
                }
                try {
                    $task->setResult($task->execute());
                } catch (Throwable $th) {
                    $task->setResult(new RuntimeException(message: 'Exception occurred during task execution', previous: $th));
                } finally {
                    $this->updateActiveAt();
                    if ($done = $this->onDone) {
                        $done($this);
                    }
                }
            }
        });

        return $this;
    }

    /**
     * @internal called by the pool before handing the worker back to the idle
     * list, the idle list relies on activeAt being frozen while a worker idles
     */
    public function updateActiveAt(int $at = 0): void
    {
        if ($at != 0) {
            $this->activeAt = $at;
            return;
        }
        $this->activeAt = (int) (microtime(true) * 1000);
    }

    public function activeAt(): int
    {
        return $this->activeAt;
    }

    public function stop(): void
    {
        $this->running = false;
        $this->channel->close();
    }
}
