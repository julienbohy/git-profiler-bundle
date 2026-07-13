<?php

declare(strict_types=1);

namespace JulienBohy\GitProfilerBundle\Tests\DataCollector;

use JulienBohy\GitProfilerBundle\DataCollector\GitDataCollector;
use JulienBohy\GitProfilerBundle\Git\ChangedFile;
use JulienBohy\GitProfilerBundle\Git\GitInfo;
use JulienBohy\GitProfilerBundle\Git\GitRepositoryInterface;
use JulienBohy\GitProfilerBundle\Git\UnpushedCommit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class GitDataCollectorTest extends TestCase
{
    public function testCollectsAvailableGitInfo(): void
    {
        $collector = $this->collectorReturning(new GitInfo('main', 'abc1234', true));

        self::assertTrue($collector->isAvailable());
        self::assertSame('main', $collector->getBranch());
        self::assertSame('abc1234', $collector->getShortCommit());
        self::assertTrue($collector->isDirty());
    }

    public function testCollectsExtendedGitInfoAsScalars(): void
    {
        $info = new GitInfo(
            'main',
            'abc1234',
            true,
            workingFiles: [
                // Même chemin, indexé ET non indexé : ne doit compter qu'une fois.
                new ChangedFile('src/App.php', 'modified', 'staged'),
                new ChangedFile('src/App.php', 'modified', 'unstaged'),
                new ChangedFile('new.txt', 'untracked', 'untracked'),
            ],
            hasUpstream: true,
            unpushedCommits: [
                new UnpushedCommit('aaaaaaa', 'feature A', 'Alice', new \DateTimeImmutable('2026-07-13T10:00:00+00:00')),
                new UnpushedCommit('bbbbbbb', 'feature B', 'Bob', new \DateTimeImmutable('2026-07-13T11:00:00+00:00')),
            ],
            unpushedFiles: [
                new ChangedFile('src/App.php', 'modified', 'committed', additions: 3, deletions: 1),
            ],
        );

        $collector = $this->collectorReturning($info);

        self::assertTrue($collector->hasUpstream());
        self::assertSame(2, $collector->getChangedFilesCount());
        self::assertSame(2, $collector->getUnpushedCommitsCount());

        // Les données exposées au template doivent être des scalaires (profiler sérialisable).
        $workingFiles = $collector->getWorkingFiles();
        self::assertCount(3, $workingFiles);
        self::assertSame('src/App.php', $workingFiles[0]['path']);
        self::assertSame('staged', $workingFiles[0]['stage']);

        $commits = $collector->getUnpushedCommits();
        self::assertCount(2, $commits);
        self::assertSame('aaaaaaa', $commits[0]['shortHash']);
        self::assertIsString($commits[0]['date']);
        self::assertSame('2026-07-13T10:00:00+00:00', $commits[0]['date']);

        $unpushedFiles = $collector->getUnpushedFiles();
        self::assertCount(1, $unpushedFiles);
        self::assertSame(3, $unpushedFiles[0]['additions']);
    }

    public function testDegradesGracefullyWhenNotAGitRepository(): void
    {
        $collector = $this->collectorReturning(null);

        self::assertFalse($collector->isAvailable());
        self::assertNull($collector->getBranch());
        self::assertNull($collector->getShortCommit());
        self::assertFalse($collector->isDirty());
        self::assertSame([], $collector->getWorkingFiles());
        self::assertSame([], $collector->getUnpushedCommits());
        self::assertSame([], $collector->getUnpushedFiles());
        self::assertSame(0, $collector->getChangedFilesCount());
        self::assertSame(0, $collector->getUnpushedCommitsCount());
        self::assertFalse($collector->hasUpstream());
    }

    public function testExposesStableNameAndTemplate(): void
    {
        self::assertSame('git_profiler', $this->collectorReturning(null)->getName());
        self::assertSame('@GitProfiler/Collector/git.html.twig', GitDataCollector::getTemplate());
    }

    private function collectorReturning(?GitInfo $info): GitDataCollector
    {
        $repository = $this->createStub(GitRepositoryInterface::class);
        $repository->method('read')->willReturn($info);

        $collector = new GitDataCollector($repository);
        $collector->collect(new Request(), new Response());

        return $collector;
    }
}
