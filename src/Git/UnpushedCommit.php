<?php

declare(strict_types=1);

namespace JulienBohy\GitProfilerBundle\Git;

/**
 * Un commit local en avance sur le remote (pas encore poussé), immuable.
 */
final readonly class UnpushedCommit
{
    public function __construct(
        public string $shortHash,
        public string $subject,
        public string $author,
        public \DateTimeImmutable $date,
    ) {
    }
}
