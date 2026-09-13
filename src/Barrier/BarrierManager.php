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

        $barrier = self::get($key);
        if (is_null($barrier) || $barrier->broken()) {
            // a broken barrier has done serving its batch, take over the key with a new generation
            $barrier = new CounterBarrier($parties);
            self::set($key, $barrier);
        }

        try {
            $barrier->await($timeout);
            return $caller();
        } finally {
            // remove the entry only when it is still this barrier: a new
            // generation may already be forming on the same key
            if ($barrier->broken() && self::get($key) === $barrier) {
                unset(self::$container[$key]);
            }
        }
    }
}
