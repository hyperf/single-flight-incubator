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

use Hyperf\Context\Context as HyperfContext;

class Context
{
    public const WORKERPOOL_NAME = 'worker_pool_name';

    public const WORKERPOOL_TIMEOUT = 'worker_pool_timeout';

    public const WORKERPOOL_SYNC = 'worker_pool_sync';

    public static function withName(string $name): string
    {
        return HyperfContext::set(self::WORKERPOOL_NAME, $name);
    }

    public static function name(): string
    {
        return HyperfContext::get(self::WORKERPOOL_NAME, '');
    }

    public static function withTimeout(float $timeout): float
    {
        return HyperfContext::set(self::WORKERPOOL_TIMEOUT, $timeout);
    }

    public static function timeout(): float
    {
        return HyperfContext::get(self::WORKERPOOL_TIMEOUT, -1);
    }

    public static function withSync(bool $sync): bool
    {
        return HyperfContext::set(self::WORKERPOOL_SYNC, $sync);
    }

    public static function sync(): bool
    {
        return HyperfContext::get(self::WORKERPOOL_SYNC, true);
    }

    public static function clear(string $key): void
    {
        HyperfContext::destroy($key);
    }

    public static function clearAll(): void
    {
        $ctx = HyperfContext::getContainer();
        unset($ctx[self::WORKERPOOL_NAME], $ctx[self::WORKERPOOL_TIMEOUT], $ctx[self::WORKERPOOL_SYNC]);
    }
}
