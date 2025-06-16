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

use Hyperf\Incubator\WorkerPool\Exception\ConfigException;

class Config
{
    public const MAX_CAPACITY = 10000;

    public const MAX_BLOCKS = 1000;

    public const QUEUE_POOL = 'queue';

    public const STACK_POOL = 'stack';

    protected int $capacity = 10;

    protected string $poolType = self::STACK_POOL;

    protected bool $preSpawn = false;

    protected int $maxBlocks = -1;

    protected int $gcIntervalMs = -1;

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): self
    {
        $this->capacity = $capacity;
        return $this;
    }

    public function getPoolType(): string
    {
        return $this->poolType;
    }

    public function setPoolType(string $poolType): self
    {
        $this->poolType = $poolType;
        return $this;
    }

    public function isPreSpawn(): bool
    {
        return $this->preSpawn;
    }

    public function setPreSpawn(bool $preSpawn): self
    {
        $this->preSpawn = $preSpawn;
        return $this;
    }

    public function getMaxBlocks(): int
    {
        return $this->maxBlocks;
    }

    public function setMaxBlocks(int $maxBlocks): self
    {
        $this->maxBlocks = $maxBlocks;
        return $this;
    }

    public function getGcIntervalMs(): int
    {
        return $this->gcIntervalMs;
    }

    public function setCollectInactiveWorker(int $gcIntervalMs): self
    {
        $this->gcIntervalMs = $gcIntervalMs;
        return $this;
    }

    public function check(): void
    {
        if ($this->poolType != self::QUEUE_POOL && $this->poolType != self::STACK_POOL) {
            throw new ConfigException('Invalid pool type, only "stack" or "queue" supported');
        }

        if ($this->capacity > self::MAX_CAPACITY) {
            throw new ConfigException('Capacity exceeds maximum limit of ' . self::MAX_CAPACITY);
        }

        if ($this->maxBlocks > self::MAX_BLOCKS) {
            throw new ConfigException('Maximum blocks exceeds limit of ' . self::MAX_BLOCKS);
        }

        if ($this->gcIntervalMs != -1 && $this->gcIntervalMs < 100) {
            throw new ConfigException('Invalid collect inactive worker ms');
        }
    }
}
