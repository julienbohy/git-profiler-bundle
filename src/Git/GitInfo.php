<?php

declare(strict_types=1);

namespace JulienBohy\GitProfilerBundle\Git;

/**
 * État Git immuable du dépôt courant.
 */
final readonly class GitInfo
{
    /**
     * @param list<ChangedFile>    $workingFiles    fichiers non commités du working tree
     * @param list<UnpushedCommit> $unpushedCommits commits locaux en avance sur le remote
     * @param list<ChangedFile>    $unpushedFiles   fichiers touchés par les commits non pushés
     */
    public function __construct(
        public string $branch,
        public string $shortCommit,
        public bool $isDirty,
        public array $workingFiles = [],
        public bool $hasUpstream = false,
        public array $unpushedCommits = [],
        public array $unpushedFiles = [],
    ) {
    }
}
