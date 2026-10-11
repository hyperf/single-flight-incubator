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

namespace Hyperf\Incubator\Semaphore;

use Hyperf\Incubator\Semaphore\Exception\RuntimeException;
use Hyperf\Incubator\Semaphore\Exception\TimeoutException;
use Hyperf\Incubator\Semaphore\Exception\TokenException;
use SplObjectStorage;

class Semaphore
{
    private int $current = 0;

    /**
     * Waiters are granted in the order they are attached, which relies on
     * the insertion order iteration of SplObjectStorage, and this behavior
     * is guaranteed by the WaitersAreGrantedInFIFOOrder test case.
     */
    /** @var SplObjectStorage<Waiter, null> */
    private SplObjectStorage $waiters;

    public function __construct(protected int $tokens)
    {
        $this->waiters = new SplObjectStorage();
    }

    /**
     * @throws TimeoutException
     */
    public function acquire(int $tokens, float $timeout = -1): void
    {
        if ($tokens < 1) {
            throw new TokenException('The number of tokens must be greater than or equal to 1');
        }

        if ($tokens > $this->tokens) {
            throw new TokenException('The number of tokens requested exceeds the semaphore size');
        }

        if ($this->tokens - $this->current >= $tokens && $this->waiters->count() == 0) {
            $this->current += $tokens;
            return;
        }

        $waiter = new Waiter($tokens);
        $this->waiters->attach($waiter);

        try {
            $waiter->wait($timeout);
        } catch (TimeoutException $ex) {
            // leave the queue first, then pass the grant chance to the successor waiters
            $this->waiters->detach($waiter);
            if ($this->tokens > $this->current) {
                $this->notifyWaiters();
            }
            throw $ex;
        }
    }

    public function tryAcquire(int $tokens): bool
    {
        if ($this->tokens - $this->current >= $tokens && $this->waiters->count() == 0) {
            $this->current += $tokens;
            return true;
        }

        return false;
    }

    public function release(int $tokens): void
    {
        if ($this->current < $tokens) {
            throw new RuntimeException('Semaphore released more than held');
        }
        $this->current -= $tokens;

        $this->notifyWaiters();
    }

    private function notifyWaiters(): void
    {
        while (true) {
            $waiter = $this->frontWaiter();
            if (is_null($waiter)) {
                break;
            }

            if ($this->tokens - $this->current < $waiter->tokens()) {
                break;
            }

            $this->current += $waiter->tokens();
            $this->waiters->detach($waiter);
            $waiter->resume();
        }
    }

    private function frontWaiter(): ?Waiter
    {
        $this->waiters->rewind();
        if (! $this->waiters->valid()) {
            return null;
        }

        return $this->waiters->current();
    }
}
