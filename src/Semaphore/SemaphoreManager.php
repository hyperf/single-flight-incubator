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

    /**
     * @var null|WeakMap<Semaphore, int>
     *
     * Refcount side table keyed by semaphore objects: entries are never
     * unset manually, they evaporate automatically upon object destruction,
     * so the table never leaks
     */
    public static ?WeakMap $refs = null;

    public static function getSema(string $key, int $tokens): Semaphore
    {
        if (is_null(self::$refs)) {
            self::$refs = new WeakMap();
        }
        $refs = self::$refs;

        if (! self::has($key)) {
            $sema = new Semaphore($tokens);
            self::set($key, $sema);
            $refs[$sema] = 0;
        }

        $sema = self::get($key);
        ++$refs[$sema];

        return $sema;
    }

    public static function remove(string $key): bool
    {
        $sema = self::get($key);
        if (is_null($sema) || is_null(self::$refs)) {
            return true;
        }
        $refs = self::$refs;

        if (--$refs[$sema] > 0) {
            return false;
        }

        unset(self::$container[$key]);

        return true;
    }
}
