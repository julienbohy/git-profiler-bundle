<?php

declare(strict_types=1);

namespace JulienBohy\GitProfilerBundle\Git;

/**
 * État Git immuable du dépôt courant.
 */
final readonly class GitInfo
{
    public function __construct(
        public string $branch,
        public string $shortCommit,
        public bool $isDirty,
    ) {
    }
}
