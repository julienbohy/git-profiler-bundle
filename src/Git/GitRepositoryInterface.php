<?php

declare(strict_types=1);

namespace JulienBohy\GitProfilerBundle\Git;

interface GitRepositoryInterface
{
    /**
     * Lit l'état Git du répertoire de travail.
     *
     * Retourne null si ce n'est pas un dépôt Git ou si la commande « git »
     * est indisponible (dégradation propre, jamais d'exception).
     */
    public function read(): ?GitInfo;
}
