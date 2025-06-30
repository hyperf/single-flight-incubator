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

use Hyperf\Context\Context as HyperfContext;

class Context
{
    public const BARRIER_KEY = 'barrier_key';

    public const BARRIER_PARTIES = 'barrier_parties';

    public const BARRIER_TIMEOUT = 'barrier_timeout';

    public static function withKey(string $key): string
    {
        return HyperfContext::set(self::BARRIER_KEY, $key);
    }

    public static function key(): string
    {
        return HyperfContext::get(self::BARRIER_KEY, '');
    }

    public static function withParties(int $parties): int
    {
        return HyperfContext::set(self::BARRIER_PARTIES, $parties);
    }

    public static function parties(): int
    {
        return HyperfContext::get(self::BARRIER_PARTIES, 0);
    }

    public static function withTimeout(float $timeout): float
    {
        return HyperfContext::set(self::BARRIER_TIMEOUT, $timeout);
    }

    public static function timeout(): float
    {
        return HyperfContext::get(self::BARRIER_TIMEOUT, -1);
    }

    public static function clear(string $key): void
    {
        HyperfContext::destroy($key);
    }

    public static function clearAll(): void
    {
        $ctx = HyperfContext::getContainer();
        unset($ctx[self::BARRIER_KEY], $ctx[self::BARRIER_PARTIES], $ctx[self::BARRIER_TIMEOUT]);
    }
}
