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

class Task implements TaskInterface
{
    private Channel $retChan;

    private bool $done = false;

    private mixed $ret;

    public function __construct(protected Closure $biz, protected bool $sync = false)
    {
        $this->retChan = new Channel();
    }

    public function isSync(): bool
    {
        return $this->sync;
    }

    public function execute(): mixed
    {
        return ($this->biz)();
    }

    public function setResult(mixed $ret): void
    {
        if ($this->done) {
            return;
        }

        $this->done = true;
        $this->ret = $ret;
        $this->retChan->close();
    }

    public function waitResult(): mixed
    {
        if ($this->done) {
            return $this->ret;
        }

        $this->retChan->pop();

        return $this->ret;
    }
}
