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

namespace HyperfTest\Incubator\Stubs\WorkerPool;

use Hyperf\Incubator\WorkerPool\TaskInterface;

class RetTask implements TaskInterface
{
    public function __construct(protected mixed $ret, protected bool $sync = false)
    {
    }

    public function execute(): mixed
    {
        return $this->ret;
    }

    public function setResult(mixed $ret): void
    {
        $this->ret = $ret;
    }

    public function waitResult(): mixed
    {
        return $this->ret;
    }

    public function isSync(): bool
    {
        return $this->sync;
    }
}
