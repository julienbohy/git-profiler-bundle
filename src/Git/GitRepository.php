<?php

declare(strict_types=1);

namespace JulienBohy\GitProfilerBundle\Git;

use Gitonomy\Git\Diff\File;
use Gitonomy\Git\Reference\Branch;
use Gitonomy\Git\Repository;
use Gitonomy\Git\WorkingCopy;

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

            $workingFiles = $this->collectWorkingFiles($repository->getWorkingCopy());

            [$hasUpstream, $unpushedCommits, $unpushedFiles] = $this->collectUnpushed($repository);

            return new GitInfo(
                $branch,
                $shortCommit,
                $workingFiles !== [],
                $workingFiles,
                $hasUpstream,
                $unpushedCommits,
                $unpushedFiles,
            );
        } catch (\Throwable) {
            // Pas un dépôt Git, dépôt sans commit, ou git indisponible : dégradation propre.
            return null;
        }
    }

    /**
     * @return list<ChangedFile>
     */
    private function collectWorkingFiles(WorkingCopy $workingCopy): array
    {
        $files = [];

        foreach ($workingCopy->getDiffStaged()->getFiles() as $file) {
            $files[] = $this->changedFile($file, 'staged');
        }

        foreach ($workingCopy->getDiffPending()->getFiles() as $file) {
            $files[] = $this->changedFile($file, 'unstaged');
        }

        foreach ($workingCopy->getUntrackedFiles() as $path) {
            $files[] = new ChangedFile($path, 'untracked', 'untracked');
        }

        return $files;
    }

    /**
     * Lit les commits en avance sur l'upstream et les fichiers qu'ils touchent.
     *
     * Isolé dans son propre try/catch : sans upstream configuré (`@{u}` absent),
     * gitonomy lève une exception qui ne doit pas casser la lecture branche/commit.
     *
     * @return array{0: bool, 1: list<UnpushedCommit>, 2: list<ChangedFile>}
     */
    private function collectUnpushed(Repository $repository): array
    {
        try {
            // Lève si aucun upstream n'est configuré (repository en mode debug par défaut).
            $repository->run('rev-parse', ['--verify', '--quiet', '@{u}']);

            $log = $repository->getLog('@{u}..HEAD');

            $commits = [];
            foreach ($log->getCommits() as $commit) {
                $commits[] = new UnpushedCommit(
                    $commit->getFixedShortHash(7),
                    $commit->getSubjectMessage(),
                    $commit->getAuthorName(),
                    \DateTimeImmutable::createFromInterface($commit->getAuthorDate()),
                );
            }

            $files = [];
            foreach ($log->getDiff()->getFiles() as $file) {
                $files[] = $this->changedFile($file, 'committed');
            }

            return [true, $commits, $files];
        } catch (\Throwable) {
            // Pas d'upstream, pas de remote, ou HEAD détaché : dégradation locale.
            return [false, [], []];
        }
    }

    /**
     * @param 'staged'|'unstaged'|'committed' $stage
     */
    private function changedFile(File $file, string $stage): ChangedFile
    {
        return new ChangedFile(
            $file->getName(),
            $this->statusOf($file),
            $stage,
            $file->isRename() ? $file->getOldName() : null,
            $file->getAdditions(),
            $file->getDeletions(),
        );
    }

    /**
     * @return 'added'|'modified'|'deleted'|'renamed'
     */
    private function statusOf(File $file): string
    {
        return match (true) {
            $file->isRename() => 'renamed',
            $file->isCreation() => 'added',
            $file->isDeletion() => 'deleted',
            default => 'modified',
        };
    }
}
