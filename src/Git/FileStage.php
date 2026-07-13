<?php

declare(strict_types=1);

namespace JulienBohy\GitProfilerBundle\Git;

/**
 * Emplacement d'un fichier modifié dans le cycle Git.
 */
enum FileStage: string
{
    case Staged = 'staged';
    case Unstaged = 'unstaged';
    case Untracked = 'untracked';
    case Committed = 'committed';

    public function label(): string
    {
        return match ($this) {
            self::Staged => 'Indexé',
            self::Unstaged => 'Non indexé',
            self::Untracked => 'Non suivi',
            self::Committed => 'Commité',
        };
    }
}
