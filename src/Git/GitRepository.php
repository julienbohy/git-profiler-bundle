<?php

declare(strict_types=1);

namespace JulienBohy\GitProfilerBundle\Git;

use Gitonomy\Git\Reference\Branch;
use Gitonomy\Git\Repository;

final readonly class GitRepository implements GitRepositoryInterface
{
    public function __construct(private string $workingDirectory)
    {
    }

    public function read(): ?GitInfo
    {
        try {
            $repository = new Repository($this->workingDirectory);

            $head = $repository->getHead();
            $branch = $head instanceof Branch ? $head->getName() : 'HEAD';
            $shortCommit = $repository->getHeadCommit()->getShortHash();

            $workingCopy = $repository->getWorkingCopy();
            $isDirty = [] !== $workingCopy->getDiffPending()->getFiles()
                || [] !== $workingCopy->getDiffStaged()->getFiles()
                || [] !== $workingCopy->getUntrackedFiles();

            return new GitInfo($branch, $shortCommit, $isDirty);
        } catch (\Throwable) {
            // Pas un dépôt Git, dépôt sans commit, ou git indisponible : dégradation propre.
            return null;
        }
    }
}
