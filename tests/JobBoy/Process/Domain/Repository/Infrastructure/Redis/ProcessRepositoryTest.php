<?php

namespace Tests\JobBoy\Process\Domain\Repository\Infrastructure\Redis;

use JobBoy\Process\Domain\Entity\Factory\ProcessFactory;
use JobBoy\Process\Domain\Entity\Infrastructure\TouchCallback\Process;
use JobBoy\Process\Domain\Repository\Infrastructure\Redis\ProcessRepository;
use JobBoy\Process\Domain\Repository\Infrastructure\Redis\RedisFactory;
use JobBoy\Process\Domain\Repository\ProcessRepositoryInterface;
use JobBoy\Process\Domain\Repository\Test\ProcessRepositoryInterfaceTest;
use JobBoy\Retryer\Domain\DefaultRetryer;
use JobBoy\Retryer\Domain\FibonacciRetryWaitManager;
use Ramsey\Uuid\Uuid;

class ProcessRepositoryTest extends ProcessRepositoryInterfaceTest
{


    protected function createRepository(): ProcessRepositoryInterface
    {
        $redisFactory = new RedisFactory('redis');
        $redis = $redisFactory->create();
        $retryer = new DefaultRetryer(
            new FibonacciRetryWaitManager(1),
            5
        );

        $id = Uuid::uuid4();

        return new ProcessRepository($retryer, $redis, 'test.jobboy.processes.' . $id);
    }

    protected function createFactory(): ProcessFactory
    {
        return new ProcessFactory(Process::class);
    }
}
