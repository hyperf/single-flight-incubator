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

namespace HyperfTest\Incubator\Cases\WorkerPool;

use Hyperf\Incubator\WorkerPool\Exception\RuntimeException;
use Hyperf\Incubator\WorkerPool\Pool\Contracts\WithNodeInterface;
use Hyperf\Incubator\WorkerPool\Task;
use Hyperf\Incubator\WorkerPool\Worker;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
#[CoversNothing]
class WorkerTest extends TestCase
{
    public function testWorkerRun()
    {
        $worker = new Worker();
        $worker = $worker->run();

        $this->assertInstanceOf(WithNodeInterface::class, $worker);
        $worker->stop();
    }

    public function testWorkerSubmit()
    {
        $worker = new Worker();
        $worker = $worker->run();

        $result = $worker->submit(new Task(static fn () => 'test', sync: true));

        $this->assertEquals('test', $result);
        $worker->stop();
    }

    public function testWorkerActiveAt()
    {
        $worker = new Worker();
        $timestamp = time();
        $worker->updateActiveAt($timestamp);

        $this->assertEquals($timestamp, $worker->activeAt());
        $worker->stop();
    }

    public function testWorkerStop()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Worker already stopped');

        $worker = new Worker();
        $worker = $worker->run();
        $worker->stop();

        $worker->submit(new Task(static fn () => 'test'));
    }
}
