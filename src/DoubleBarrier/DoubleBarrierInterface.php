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

interface DoubleBarrierInterface
{
    public function enter(float $timeout = -1): void;

    public function leave(float $timeout = -1): void;
}
