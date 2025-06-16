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
use Hyperf\Incubator\Semaphore\List\Queue;

class Semaphore
{
    private int $current = 0;

    private Queue $waiters;

    public function __construct(protected int $tokens)
    {
        $this->waiters = new Queue();
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

        if ($this->tokens - $this->current >= $tokens && $this->waiters->len() == 0) {
            $this->current += $tokens;
            return;
        }

        $waiter = new Waiter($tokens);
        $node = $this->waiters->enqueue($waiter);

        try {
            $waiter->wait($timeout);
        } catch (TimeoutException $ex) {
            $firstNode = $this->waiters->peek();
            if ($node === $firstNode && $this->tokens > $this->current) {
                $this->notifyWaiters();
            }
            $this->waiters->remove($node);
            throw $ex;
        }
    }

    public function tryAcquire(int $tokens): bool
    {
        if ($this->tokens - $this->current >= $tokens && $this->waiters->len() == 0) {
            $this->current += $tokens;
            return true;
        }

        return false;
    }

    public function release(int $tokens): void
    {
        $this->current -= $tokens;
        if ($this->current < 0) {
            throw new RuntimeException('Semaphore released more than held');
        }

        $this->notifyWaiters();
    }

    private function notifyWaiters(): void
    {
        while (true) {
            $node = $this->waiters->peek();
            if (is_null($node)) {
                break;
            }

            /** @var Waiter $waiter */
            $waiter = $node->value();
            if ($this->tokens - $this->current < $waiter->tokens()) {
                break;
            }

            $this->current += $waiter->tokens();
            $this->waiters->remove($node);
            $waiter->resume();
        }
    }
}
