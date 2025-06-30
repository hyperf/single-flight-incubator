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

use Hyperf\Support\Traits\Container;
use WeakMap;

class SemaphoreManager
{
    use Container;

    public static ?WeakMap $refs = null;

    public static function getSema(string $key, int $tokens): Semaphore
    {
        if (is_null(self::$refs)) {
            self::$refs = new WeakMap();
        }

        if (! self::has($key)) {
            $sema = new Semaphore($tokens);
            self::set($key, $sema);
            self::$refs[$sema] = 0;
        }

        $sema = self::get($key);
        ++self::$refs[$sema];

        return $sema;
    }

    public static function remove(string $key): bool
    {
        $sema = self::get($key);
        if (is_null($sema) || is_null(self::$refs)) {
            return true;
        }

        --self::$refs[$sema];
        if (self::$refs[$sema] <= 0) {
            unset($sema, self::$container[$key]);
            return true;
        }

        return false;
    }
}
