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
use Hyperf\Incubator\DoubleBarrier\Exception\DoubleBarrierException;
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

    private bool $queueFailed = false;

    private bool $done = false;

    private Channel $enterChan;

    private Channel $leaveChan;

    public function __construct(protected int $parties)
    {
        $this->enterChan = new Channel();
    }

    public function __destruct()
    {
        if (! $this->done) {
            throw new RuntimeException('DoubleBarrier was not properly leaved before destruction');
        }
    }

    /**
     * @throws DoubleBarrierException
     */
    public function enter(float $timeout = -1): void
    {
        if ($this->leaving) {
            throw new EnterException('Cannot enter barrier while in leaving state');
        }
        if (++$this->inFences == $this->parties) {
            $this->leaving = true;
            $this->leaveChan = new Channel();
            $this->enterChan->close();
            return;
        }

        $ret = $this->enterChan->pop($timeout);
        if ($ret === false && $this->enterChan->isTimeout()) {
            $this->done = true;
            throw new EnterException(message: 'Timeout while waiting others to enter barrier', previous: new TimeoutException());
        }
    }

    /**
     * @throws DoubleBarrierException
     */
    public function execute(callable $callback, float $enterTimeout = -1, float $leaveTimeout = -1): mixed
    {
        try {
            $this->enter($enterTimeout);
            return call($callback);
        } catch (Throwable $th) {
            if ($th instanceof DoubleBarrierException) {
                $this->queueFailed = true;
                throw $th;
            }
            throw new RuntimeException(message: 'An exception occurred while executing callback', previous: $th);
        } finally {
            $this->leave($leaveTimeout);
        }
    }

    /**
     * @throws DoubleBarrierException
     */
    public function leave(float $timeout = -1): void
    {
        if ($this->queueFailed) {
            return;
        }
        if (! $this->leaving) {
            throw new LeaveException('Cannot leave barrier before fully entering');
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
            throw new LeaveException(message: 'Timeout while waiting others to leave barrier', previous: new TimeoutException());
        }
    }
}
