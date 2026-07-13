<?php

declare(strict_types=1);

namespace JulienBohy\GitProfilerBundle\Git;

/**
 * Un fichier modifié, immuable.
 *
 * Sert aussi bien pour le working tree (staged / unstaged / untracked) que pour
 * les fichiers touchés par des commits non pushés (stage « committed »).
 */
final readonly class ChangedFile
{
    public function __construct(
        public string $path,
        public FileStatus $status,
        public FileStage $stage,
        public ?string $oldPath = null,
        public int $additions = 0,
        public int $deletions = 0,
    ) {
    }
}
