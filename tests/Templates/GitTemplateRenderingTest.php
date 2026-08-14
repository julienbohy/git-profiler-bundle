<?php

declare(strict_types=1);

namespace JulienBohy\GitProfilerBundle\Tests\Templates;

use JulienBohy\GitProfilerBundle\DataCollector\GitDataCollector;
use JulienBohy\GitProfilerBundle\Git\GitInfo;
use JulienBohy\GitProfilerBundle\Git\GitRepositoryInterface;
use JulienBohy\GitProfilerBundle\Git\GraphCommit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

/**
 * Renders the collector template with the profiler layout stubbed out, so the
 * blocks are executed with real data without booting a kernel.
 */
final class GitTemplateRenderingTest extends TestCase
{
    public function testRendersTheCommitGraph(): void
    {
        $rootHash = str_repeat('c', 40);

        $info = new GitInfo(
            'main',
            'abc1234',
            false,
            hasUpstream: true,
            graphCommits: [
                new GraphCommit(str_repeat('a', 40), 'aaaaaaa', [$rootHash], 'local work', 'Alice', new \DateTimeImmutable('2026-08-14T10:00:00+00:00'), isHead: true),
                new GraphCommit(str_repeat('b', 40), 'bbbbbbb', [$rootHash], 'remote work', 'Bob', new \DateTimeImmutable('2026-08-14T09:00:00+00:00'), isUpstream: true, isPushed: true),
                new GraphCommit($rootHash, 'ccccccc', [], 'init', 'Alice', new \DateTimeImmutable('2026-08-14T08:00:00+00:00'), isPushed: true),
            ],
            upstreamRef: 'origin/main',
        );

        $html = $this->render($info);

        self::assertStringContainsString('Commit graph', $html);
        self::assertStringContainsString('local work', $html);
        // Dots are drawn for the three commits.
        self::assertSame(3, substr_count($html, '<circle'));
        // The diverged branch produces at least one curved segment — fill="none"
        // is unique to the bezier curves (the icon SVG also contains a <path>).
        self::assertStringContainsString('fill="none"', $html);
        // Refs are labelled.
        self::assertStringContainsString('HEAD → main', $html);
        self::assertStringContainsString('origin/main', $html);
    }

    public function testFillsAllDotsWhenThereIsNoUpstream(): void
    {
        // Without an upstream, "pushed" is meaningless: no dot may render hollow
        // (the hollow-dot legend is hidden in that state).
        $rootHash = str_repeat('c', 40);

        $html = $this->render(new GitInfo(
            'main',
            'abc1234',
            false,
            graphCommits: [
                new GraphCommit(str_repeat('a', 40), 'aaaaaaa', [$rootHash], 'local work', 'Alice', new \DateTimeImmutable('2026-08-14T10:00:00+00:00'), isHead: true),
                new GraphCommit($rootHash, 'ccccccc', [], 'init', 'Alice', new \DateTimeImmutable('2026-08-14T08:00:00+00:00')),
            ],
        ));

        self::assertStringContainsString('Commit graph', $html);
        self::assertStringNotContainsString('var(--base-0', $html);
        self::assertStringNotContainsString('Hollow dots', $html);
    }

    public function testDetachedHeadShowsAPlainHeadBadge(): void
    {
        $html = $this->render(new GitInfo(
            'HEAD',
            'abc1234',
            false,
            graphCommits: [
                new GraphCommit(str_repeat('a', 40), 'aaaaaaa', [], 'detached work', 'Alice', new \DateTimeImmutable('2026-08-14T10:00:00+00:00'), isHead: true),
            ],
        ));

        self::assertStringNotContainsString('HEAD → HEAD', $html);
        self::assertStringContainsString('git-badge-head">HEAD<', $html);
    }

    public function testOmitsTheCommitGraphWhenThereAreNoGraphCommits(): void
    {
        $html = $this->render(new GitInfo('main', 'abc1234', false));

        self::assertStringNotContainsString('Commit graph', $html);
    }

    private function render(GitInfo $info): string
    {
        $repository = $this->createStub(GitRepositoryInterface::class);
        $repository->method('read')->willReturn($info);

        $collector = new GitDataCollector($repository);
        $collector->collect(new Request(), new Response());

        $bundleLoader = new FilesystemLoader();
        $bundleLoader->addPath(\dirname(__DIR__, 2) . '/templates', 'GitProfiler');

        $layoutStub = new ArrayLoader([
            '@WebProfiler/Profiler/layout.html.twig' => '{% block toolbar %}{% endblock %}{% block menu %}{% endblock %}{% block panel %}{% endblock %}',
            '@WebProfiler/Profiler/toolbar_item.html.twig' => '',
        ]);

        $twig = new Environment(new ChainLoader([$layoutStub, $bundleLoader]));

        return $twig->render('@GitProfiler/Collector/git.html.twig', ['collector' => $collector]);
    }
}
