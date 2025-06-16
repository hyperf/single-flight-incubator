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
use Hyperf\Incubator\WorkerPool\Pool\Contracts\WithNodeInterface;
use Hyperf\Incubator\WorkerPool\Pool\Node;
use Throwable;

use function Hyperf\Coroutine\go;

class Worker implements WithNodeInterface
{
    protected bool $running;

    protected int $activeAt;

    protected Channel $channel;

    protected Node $node;

    public function __construct(protected ?Closure $onDone = null)
    {
        $this->channel = new Channel();

        $this->updateActiveAt();
    }

    public function getNode(): Node
    {
        return $this->node;
    }

    public function setNode(Node $node): void
    {
        $this->node = $node;
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
                /** @var bool|TaskInterface $task */
                $task = $this->channel->pop();
                if ($task === false && $this->channel->isClosing()) {
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
