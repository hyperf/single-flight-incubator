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

use Closure;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Incubator\Barrier\Exception\RuntimeException;
use Hyperf\Support\Traits\Container;

class BarrierManager
{
    use Container;

    public static function counterCall(string $key, int $parties, Closure $caller, float $timeout = -1): mixed
    {
        if ($parties <= 1) {
            throw new RuntimeException('Parties must be greater than 1');
        }
        if (! Coroutine::inCoroutine()) {
            throw new RuntimeException('Barrier can only be used in coroutine environment');
        }

        if (! self::has($key)) {
            $barrier = new CounterBarrier($parties);
            self::set($key, $barrier);
        }

        /** @var BarrierInterface $barrier */
        $barrier = self::get($key);
        try {
            $barrier->await($timeout);
            return $caller();
        } finally {
            if ($barrier->broken() && self::has($key)) {
                unset(self::$container[$key]);
            }
        }
    }
}
