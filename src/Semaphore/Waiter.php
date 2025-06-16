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

use Hyperf\Engine\Channel;
use Hyperf\Incubator\Semaphore\Exception\TimeoutException;

class Waiter
{
    private Channel $ready;

    public function __construct(protected int $token)
    {
    }

    public function wait(float $timeout = -1): void
    {
        $this->ready = new Channel(1);
        $ret = $this->ready->pop($timeout);
        if ($ret === false && $this->ready->isTimeout()) {
            throw new TimeoutException("Acquire for semaphore timeout for {$this->token} tokens");
        }
    }

    public function tokens(): int
    {
        return $this->token;
    }

    public function resume(): void
    {
        $this->ready->push(true);
    }
}
