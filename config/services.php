<?php

declare(strict_types=1);

use JulienBohy\GitProfilerBundle\DataCollector\GitDataCollector;
use JulienBohy\GitProfilerBundle\Git\GitRepository;
use JulienBohy\GitProfilerBundle\Git\GitRepositoryInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(GitRepository::class)
        ->args([param('kernel.project_dir')]);

    $services->alias(GitRepositoryInterface::class, GitRepository::class);

    $services->set(GitDataCollector::class)
        ->args([service(GitRepositoryInterface::class)])
        ->tag('data_collector', [
            'id' => 'git_profiler',
            'template' => '@GitProfiler/Collector/git.html.twig',
        ]);
};
