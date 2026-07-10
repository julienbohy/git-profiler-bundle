<?php

declare(strict_types=1);

namespace JulienBohy\GitProfilerBundle\DataCollector;

use JulienBohy\GitProfilerBundle\Git\GitRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class GitDataCollector extends AbstractDataCollector
{
    public function __construct(private readonly GitRepositoryInterface $repository)
    {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $info = $this->repository->read();

        $this->data = [
            'available' => $info !== null,
            'branch' => $info?->branch,
            'shortCommit' => $info?->shortCommit,
            'dirty' => $info?->isDirty ?? false,
        ];
    }

    public function getName(): string
    {
        return 'git_profiler';
    }

    public static function getTemplate(): ?string
    {
        return '@GitProfiler/Collector/git.html.twig';
    }

    public function isAvailable(): bool
    {
        return $this->data['available'] ?? false;
    }

    public function getBranch(): ?string
    {
        return $this->data['branch'] ?? null;
    }

    public function getShortCommit(): ?string
    {
        return $this->data['shortCommit'] ?? null;
    }

    public function isDirty(): bool
    {
        return $this->data['dirty'] ?? false;
    }
}
