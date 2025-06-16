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

namespace Hyperf\Incubator\WorkerPool\Heap;

interface HeapInterface
{
    public function len(): int;

    public function less(int $i, int $j): bool;

    public function swap(int $i, int $j): void;

    public function insert(mixed $item): void;

    public function top(): mixed;

    public function extract(): mixed;

    public function remove(mixed $item): mixed;

    public function fix(int $i): void;

    public function build(): void;
}
