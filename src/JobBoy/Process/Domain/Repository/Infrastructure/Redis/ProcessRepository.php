<?php

namespace JobBoy\Process\Domain\Repository\Infrastructure\Redis;

use Assert\Assertion;
use JobBoy\Process\Domain\Entity\Id\ProcessId;
use JobBoy\Process\Domain\Entity\Infrastructure\TouchCallback\Process as TouchCallbackProcess;
use JobBoy\Process\Domain\Entity\Process;
use JobBoy\Process\Domain\ProcessStatus;
use JobBoy\Process\Domain\Repository\Infrastructure\Util\ProcessRepositoryUtil;
use JobBoy\Process\Domain\Repository\ProcessRepositoryInterface;
use JobBoy\Retryer\Domain\Retryer;

class ProcessRepository implements ProcessRepositoryInterface
{
    const DEFAULT_NAMESPACE = 'jobboy-processes';

    /** @var \Redis */
    protected $redis;

    /** @var string|null */
    protected $namespace;

    protected $touchCallback;
    /** @var Retryer */
    private $retryer;

    public function __construct(
        Retryer $retryer,
        \Redis $redis,
        ?string $namespace = null
    )
    {
        $this->retryer = $retryer;
        $this->redis = $redis;
        if (!$namespace) {
            $namespace = self::DEFAULT_NAMESPACE;
        }
        $this->namespace = $namespace;

        $this->touchCallback = function (Process $process) {
            $this->onTouch($process);
        };
    }

    protected function onTouch(Process $process)
    {
        $this->_set($process);
    }

    public function add(Process $process): void
    {
        Assertion::isInstanceOf($process, TouchCallbackProcess::class);
        $this->_set($process);
    }

    public function remove(Process $process): void
    {
        $this->_unset($process);
    }


    public function byId(ProcessId $id): ?Process
    {
        return $this->_get((string)$id);
    }

    public function all(?int $start = null, ?int $length = null): array
    {
        $processes = $this->_all();

        $processes = array_values($processes);

        $processes = ProcessRepositoryUtil::sort($processes);
        $processes = ProcessRepositoryUtil::slice($processes, $start, $length);

        return $processes;
    }

    public function handled(?int $start = null, ?int $length = null): array
    {
        $processes = $this->_all();

        $processes = array_filter($processes, function (Process $process) {
            return $process->isHandled();
        });

        $processes = ProcessRepositoryUtil::sort($processes);
        $processes = ProcessRepositoryUtil::slice($processes, $start, $length);
        return $processes;
    }

    public function byStatus(ProcessStatus $status, ?int $start = null, ?int $length = null): array
    {
        $processes = $this->_all();
        $processes = array_filter($processes, function (Process $process) use ($status) {
            return $process->status()->equals($status);
        });

        $processes = ProcessRepositoryUtil::sort($processes);
        $processes = ProcessRepositoryUtil::slice($processes, $start, $length);
        return $processes;

    }


    public function stale(?\DateTimeImmutable $until = null, ?int $start = null, ?int $length = null): array
    {
        if (!$until) {
            $until = ProcessRepositoryUtil::aFewDaysAgo(self::DEFAULT_STALE_DAYS);
        }

        $processes = $this->_all();
        $processes = array_filter($processes, function (Process $process) use ($until) {
            return $process->updatedAt() < $until;
        });

        $processes = ProcessRepositoryUtil::sort($processes);
        $processes = ProcessRepositoryUtil::slice($processes, $start, $length);
        return $processes;
    }

    protected function _set(TouchCallbackProcess $process): void
    {
        $process->removeTouchCallback($this->touchCallback);

        $this->retryer->try(
            function() use ($process) {
                $this->redis->hset($this->namespace, (string)$process->id(), $process);
            }
        );

        $process->addTouchCallback($this->touchCallback);
    }

    protected function _get(string $id): ?TouchCallbackProcess
    {
        $process = $this->retryer->try(
            function() use ($id) {
                $process = $this->redis->hget($this->namespace, $id);
                Assertion::nullOrIsInstanceOf($process, Process::class, 'Redis returned an invalid value');

                return $process;
            }
        );

        if ($process === false) {
            return null;
        }

        $process->addTouchCallback($this->touchCallback);

        return $process;
    }

    protected function _unset(TouchCallbackProcess $process): void
    {
        $this->retryer->try(
            function() use ($process) {
                $this->redis->hDel($this->namespace, (string)$process->id());
            }
        );
        $process->removeTouchCallback($this->touchCallback);
    }

    protected function _all(): array
    {
        $processes = $this->retryer->try(
            function () {
                $processes = $this->redis->hGetAll($this->namespace);
                Assertion::isArray($processes, 'Redis returned a non-array value');

                return $processes;
            }
        );


        array_walk($processes, function ($process) {
            $process->addTouchCallback($this->touchCallback);
        });

        return $processes;
    }


}