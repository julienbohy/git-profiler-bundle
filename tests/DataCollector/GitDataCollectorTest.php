<?php

declare(strict_types=1);

namespace JulienBohy\GitProfilerBundle\Tests\DataCollector;

use JulienBohy\GitProfilerBundle\DataCollector\GitDataCollector;
use JulienBohy\GitProfilerBundle\Git\GitInfo;
use JulienBohy\GitProfilerBundle\Git\GitRepositoryInterface;
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

    public function testDegradesGracefullyWhenNotAGitRepository(): void
    {
        $collector = $this->collectorReturning(null);

        self::assertFalse($collector->isAvailable());
        self::assertNull($collector->getBranch());
        self::assertNull($collector->getShortCommit());
        self::assertFalse($collector->isDirty());
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
