<?php

declare(strict_types=1);

namespace JulienBohy\GitProfilerBundle\Tests\Integration;

use JulienBohy\GitProfilerBundle\DataCollector\GitDataCollector;
use JulienBohy\GitProfilerBundle\Git\GitRepository;
use JulienBohy\GitProfilerBundle\Git\GitRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie le câblage réel de config/services.php (sans booter tout le framework).
 */
final class ContainerTest extends TestCase
{
    public function testServicesAndDataCollectorTagAreRegistered(): void
    {
        $container = $this->loadContainer();

        self::assertTrue($container->hasDefinition(GitRepository::class));
        self::assertTrue($container->hasAlias(GitRepositoryInterface::class));
        self::assertTrue($container->hasDefinition(GitDataCollector::class));

        $tags = $container->getDefinition(GitDataCollector::class)->getTag('data_collector');
        self::assertCount(1, $tags);
        self::assertSame('git_profiler', $tags[0]['id']);
        self::assertSame('@GitProfiler/Collector/git.html.twig', $tags[0]['template']);
    }

    public function testCollectorIsWiredAndReadsTheRepository(): void
    {
        // La racine du bundle est un dépôt Git : le collector doit y lire des infos.
        $container = $this->loadContainer();
        $container->getDefinition(GitDataCollector::class)->setPublic(true);
        $container->compile();

        $collector = $container->get(GitDataCollector::class);
        self::assertInstanceOf(GitDataCollector::class, $collector);

        $collector->collect(new Request(), new Response());

        self::assertTrue($collector->isAvailable());
        self::assertNotSame('', (string) $collector->getBranch());
        self::assertMatchesRegularExpression('/^[0-9a-f]{7,}$/', (string) $collector->getShortCommit());

        // L'état git du dépôt du bundle n'est pas maîtrisé : on vérifie seulement
        // les types et l'absence d'exception, pas de valeurs précises.
        self::assertIsInt($collector->getChangedFilesCount());
        self::assertIsInt($collector->getUnpushedCommitsCount());
        self::assertIsBool($collector->hasUpstream());
        self::assertIsArray($collector->getWorkingFiles());
        self::assertIsArray($collector->getUnpushedCommits());
        self::assertIsArray($collector->getUnpushedFiles());
    }

    private function loadContainer(): ContainerBuilder
    {
        $bundleRoot = \dirname(__DIR__, 2);

        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $bundleRoot);

        (new PhpFileLoader($container, new FileLocator($bundleRoot . '/config')))
            ->load('services.php');

        return $container;
    }
}
