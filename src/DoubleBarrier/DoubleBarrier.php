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

namespace Hyperf\Incubator\DoubleBarrier;

use Hyperf\Engine\Channel;
use Hyperf\Incubator\DoubleBarrier\Exception\EnterException;
use Hyperf\Incubator\DoubleBarrier\Exception\LeaveException;
use Hyperf\Incubator\DoubleBarrier\Exception\RuntimeException;
use Hyperf\Incubator\DoubleBarrier\Exception\TimeoutException;
use Throwable;

use function Hyperf\Support\call;

class DoubleBarrier implements DoubleBarrierInterface
{
    private int $inFences = 0;

    private bool $leaving = false;

    private bool $broken = false;

    private bool $done = false;

    /** @var Channel<mixed> */
    private Channel $enterChan;

    /** @var Channel<mixed> */
    private Channel $leaveChan;

    public function __construct(protected int $parties)
    {
        $this->enterChan = new Channel();
        $this->leaveChan = new Channel();
    }

    /**
     * @throws EnterException
     */
    public function enter(float $timeout = -1): void
    {
        if ($this->leaving) {
            throw new EnterException('Cannot enter barrier while in leaving state');
        }
        if ($this->broken) {
            throw new EnterException('Cannot enter a broken barrier');
        }
        if (++$this->inFences == $this->parties) {
            $this->leaving = true;
            $this->enterChan->close();
            return;
        }

        $ret = $this->enterChan->pop($timeout);
        if ($ret === false && $this->enterChan->isTimeout()) {
            // release the fence and break the barrier, so the other waiters fail fast
            --$this->inFences;
            $this->broken = true;
            $this->enterChan->close();
            throw new EnterException(message: 'Timeout while waiting others to enter barrier', previous: new TimeoutException());
        }
        // @phpstan-ignore-next-line broken can be set by another party while we wait in pop
        if ($this->broken) {
            throw new EnterException(message: 'The barrier was broken by another party', previous: new TimeoutException());
        }
    }

    /**
     * @throws RuntimeException
     */
    public function execute(callable $callback, float $enterTimeout = -1, float $leaveTimeout = -1): mixed
    {
        $this->enter($enterTimeout);

        try {
            return call($callback);
        } catch (Throwable $th) {
            throw new RuntimeException(message: 'An exception occurred while executing callback', previous: $th);
        } finally {
            $this->leave($leaveTimeout);
        }
    }

    /**
     * @throws LeaveException
     */
    public function leave(float $timeout = -1): void
    {
        if (! $this->leaving) {
            throw new LeaveException('Cannot leave barrier before fully entering');
        }
        if ($this->broken) {
            throw new LeaveException(message: 'The barrier was broken by another party', previous: new TimeoutException());
        }
        if ($this->done) {
            throw new LeaveException('Cannot leave barrier that is already done');
        }
        if (--$this->inFences <= 0) {
            $this->done = true;
            $this->leaveChan->close();
            return;
        }

        $ret = $this->leaveChan->pop($timeout);
        if ($ret === false && $this->leaveChan->isTimeout()) {
            // break the barrier, so the other leavers fail fast
            $this->broken = true;
            $this->leaveChan->close();
            throw new LeaveException(message: 'Timeout while waiting others to leave barrier', previous: new TimeoutException());
        }
        // @phpstan-ignore-next-line broken can be set by another party while we wait in pop
        if ($this->broken) {
            throw new LeaveException(message: 'The barrier was broken by another party', previous: new TimeoutException());
        }
    }
}
