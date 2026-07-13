<?php

declare(strict_types=1);

namespace JulienBohy\GitProfilerBundle\Git;

/**
 * Nature d'un fichier modifié (par rapport à sa version de référence).
 */
enum FileStatus: string
{
    case Added = 'added';
    case Modified = 'modified';
    case Deleted = 'deleted';
    case Renamed = 'renamed';
    case Untracked = 'untracked';

    public function label(): string
    {
        return match ($this) {
            self::Added => 'Ajouté',
            self::Modified => 'Modifié',
            self::Deleted => 'Supprimé',
            self::Renamed => 'Renommé',
            self::Untracked => 'Non suivi',
        };
    }
}
