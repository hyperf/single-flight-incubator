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

namespace Hyperf\Incubator\WorkerPool\Pool\Contracts;

use Hyperf\Incubator\WorkerPool\Worker;
use Iterator;

interface WorkerPoolInterface
{
    public function new(): Worker;

    public function get(float $timeout = 0): ?Worker;

    public function release(Worker $worker): void;

    public function iterator(): Iterator;

    public function collect(int $at): void;

    public function stop(): void;
}
