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

use Hyperf\Incubator\WorkerPool\Exception\RuntimeException;
use Hyperf\Support\Traits\Container;

class WorkerPoolManager
{
    use Container;

    public static function setPool(string $name, WorkerPool $pool): void
    {
        if (self::has($name)) {
            throw new RuntimeException("Duplicate pool named {$name} found");
        }

        self::set($name, $pool);
    }

    public static function getPool(string $name): WorkerPool
    {
        if (! self::has($name)) {
            throw new RuntimeException("No pool named {$name} found");
        }

        return self::get($name);
    }

    public static function remove($name): void
    {
        self::get($name)?->stop();
        unset(self::$container[$name]);
    }

    public static function removeAll(): void
    {
        foreach (self::$container as $name => $pool) {
            $pool->stop();
        }
        self::clear();
    }
}
