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

namespace Hyperf\Incubator\WorkerPool\Pool\Contracts;

use Hyperf\Incubator\WorkerPool\Pool\Node;

interface WithNodeInterface
{
    public function setNode(Node $node): void;

    public function getNode(): Node;
}
