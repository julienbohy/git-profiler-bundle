<?php

declare(strict_types=1);

namespace JulienBohy\GitProfilerBundle\Git;

use Gitonomy\Git\Diff\Diff;
use Gitonomy\Git\Diff\File;
use Gitonomy\Git\Repository;
use Gitonomy\Git\WorkingCopy;

final readonly class GitRepository implements GitRepositoryInterface
{
    /**
     * Séparateur de champs pour le formatage de `git log` (unit separator, ASCII 0x1F).
     */
    private const FIELD_SEPARATOR = "\x1f";

    public function __construct(private string $workingDirectory)
    {
    }

    public function read(): ?GitInfo
    {
        try {
            $repository = new Repository($this->workingDirectory);

            // Passe par `git` en direct (et non l'API objet getHead()/getLog()) pour ne pas
            // instancier ReferenceBag/Log/RevisionList : ces classes de gitonomy 1.6 émettent
            // des deprecations via le DebugClassLoader de Symfony (annotation @return absente).
            // `rev-parse HEAD` échoue si le dépôt n'a aucun commit → dégradation via le catch.
            $shortCommit = $this->run($repository, 'rev-parse', ['--short', 'HEAD']);
            $branch = $this->run($repository, 'rev-parse', ['--abbrev-ref', 'HEAD']);

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
     * git lève une exception qui ne doit pas casser la lecture branche/commit.
     *
     * @return array{0: bool, 1: list<UnpushedCommit>, 2: list<ChangedFile>}
     */
    private function collectUnpushed(Repository $repository): array
    {
        try {
            // Lève si aucun upstream n'est configuré (repository en mode debug par défaut).
            $repository->run('rev-parse', ['--verify', '--quiet', '@{u}']);

            $format = implode(self::FIELD_SEPARATOR, ['%H', '%s', '%an', '%aI']);
            $commits = $this->parseUnpushedCommits(
                $repository->run('log', ['--format=' . $format, '@{u}..HEAD']),
            );

            $files = [];
            foreach ($this->diffFiles($repository, '@{u}..HEAD') as $file) {
                $files[] = $this->changedFile($file, 'committed');
            }

            return [true, $commits, $files];
        } catch (\Throwable) {
            // Pas d'upstream, pas de remote, ou HEAD détaché : dégradation locale.
            return [false, [], []];
        }
    }

    /**
     * @return list<UnpushedCommit>
     */
    private function parseUnpushedCommits(string $output): array
    {
        $commits = [];

        foreach (explode("\n", trim($output)) as $line) {
            if ($line === '') {
                continue;
            }

            [$hash, $subject, $author, $date] = explode(self::FIELD_SEPARATOR, $line, 4);

            $commits[] = new UnpushedCommit(
                substr($hash, 0, 7),
                $subject,
                $author,
                new \DateTimeImmutable($date),
            );
        }

        return $commits;
    }

    /**
     * Reproduit Repository::getDiff() sans instancier RevisionList (qui déclenche une
     * deprecation) : Diff::parse() est public et se contente de la sortie brute de `git diff`.
     *
     * @return list<File>
     */
    private function diffFiles(Repository $repository, string $revisions): array
    {
        $raw = $repository->run('diff', [
            '-r', '-p', '--raw', '-m', '-M', '--no-commit-id', '--full-index', $revisions,
        ]);

        return Diff::parse($raw)->getFiles();
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

    /**
     * @param list<string> $args
     */
    private function run(Repository $repository, string $command, array $args): string
    {
        return trim($repository->run($command, $args));
    }
}
