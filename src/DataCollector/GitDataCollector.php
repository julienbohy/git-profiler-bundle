<?php

declare(strict_types=1);

namespace JulienBohy\GitProfilerBundle\DataCollector;

use JulienBohy\GitProfilerBundle\Git\ChangedFile;
use JulienBohy\GitProfilerBundle\Git\GitRepositoryInterface;
use JulienBohy\GitProfilerBundle\Git\UnpushedCommit;
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
            'workingFiles' => array_map($this->flattenFile(...), $info?->workingFiles ?? []),
            'hasUpstream' => $info?->hasUpstream ?? false,
            'unpushedCommits' => array_map($this->flattenCommit(...), $info?->unpushedCommits ?? []),
            'unpushedFiles' => array_map($this->flattenFile(...), $info?->unpushedFiles ?? []),
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

    /**
     * @return list<array{path: string, status: string, statusLabel: string, stage: string, stageLabel: string, oldPath: ?string, additions: int, deletions: int}>
     */
    public function getWorkingFiles(): array
    {
        return $this->data['workingFiles'] ?? [];
    }

    /**
     * @return list<array{path: string, status: string, statusLabel: string, stage: string, stageLabel: string, oldPath: ?string, additions: int, deletions: int}>
     */
    public function getUnpushedFiles(): array
    {
        return $this->data['unpushedFiles'] ?? [];
    }

    /**
     * @return list<array{shortHash: string, subject: string, author: string, date: string}>
     */
    public function getUnpushedCommits(): array
    {
        return $this->data['unpushedCommits'] ?? [];
    }

    public function hasUpstream(): bool
    {
        return $this->data['hasUpstream'] ?? false;
    }

    public function getUnpushedCommitsCount(): int
    {
        return \count($this->getUnpushedCommits());
    }

    /**
     * Nombre de fichiers distincts du working tree (un fichier partiellement
     * indexé apparaît en staged ET unstaged mais ne compte qu'une fois).
     */
    public function getChangedFilesCount(): int
    {
        return \count(array_unique(array_column($this->getWorkingFiles(), 'path')));
    }

    /**
     * @return array{path: string, status: string, statusLabel: string, stage: string, stageLabel: string, oldPath: ?string, additions: int, deletions: int}
     */
    private function flattenFile(ChangedFile $file): array
    {
        return [
            'path' => $file->path,
            'status' => $file->status->value,
            'statusLabel' => $file->status->label(),
            'stage' => $file->stage->value,
            'stageLabel' => $file->stage->label(),
            'oldPath' => $file->oldPath,
            'additions' => $file->additions,
            'deletions' => $file->deletions,
        ];
    }

    /**
     * @return array{shortHash: string, subject: string, author: string, date: string}
     */
    private function flattenCommit(UnpushedCommit $commit): array
    {
        return [
            'shortHash' => $commit->shortHash,
            'subject' => $commit->subject,
            'author' => $commit->author,
            'date' => $commit->date->format(\DateTimeInterface::ATOM),
        ];
    }
}
