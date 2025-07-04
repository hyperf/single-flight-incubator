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

namespace Hyperf\Incubator\Barrier;

use Hyperf\Engine\Channel;
use Hyperf\Incubator\Barrier\Exception\RuntimeException;
use Hyperf\Incubator\Barrier\Exception\TimeoutException;

class CounterBarrier implements BarrierInterface
{
    private int $waiters = 0;

    private bool $broken = false;

    private Channel $channel;

    public function __construct(protected int $parties)
    {
        if ($this->parties < 1) {
            throw new RuntimeException('CounterBarrier parties must be greater than 1');
        }
        $this->channel = new Channel(1);
    }

    public function await(float $timeout = -1): void
    {
        if ($this->broken) {
            throw new RuntimeException('CounterBarrier already broken');
        }

        ++$this->waiters;
        $this->tryBreak();

        $ret = $this->channel->pop($timeout);
        if ($ret === false && $this->channel->isTimeout()) {
            --$this->waiters;
            throw new TimeoutException(sprintf('Barrier await timed out, current waiters: %d, expected parties: %d', $this->waiters, $this->parties));
        }
        --$this->waiters;
    }

    public function waiters(): int
    {
        return $this->waiters;
    }

    public function broken(): bool
    {
        return $this->broken;
    }

    private function tryBreak(): void
    {
        if ($this->waiters == $this->parties) {
            $this->broken = true;
            $this->channel->close();
        }
    }
}
