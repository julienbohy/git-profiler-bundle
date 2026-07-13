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
     * Field separator for `git log` formatting (unit separator, ASCII 0x1F).
     */
    private const FIELD_SEPARATOR = "\x1f";

    public function __construct(private string $workingDirectory)
    {
    }

    public function read(): ?GitInfo
    {
        try {
            $repository = new Repository($this->workingDirectory);

            // Use git directly (rather than the getHead()/getLog() object API) to avoid
            // instantiating ReferenceBag/Log/RevisionList: in gitonomy 1.6 these classes
            // trigger deprecations through Symfony's DebugClassLoader (missing @return).
            // `rev-parse HEAD` fails when the repository has no commit → handled by the catch.
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
            // Not a Git repository, repository without commit, or git unavailable: degrade gracefully.
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
            $files[] = $this->changedFile($file, FileStage::Staged);
        }

        foreach ($workingCopy->getDiffPending()->getFiles() as $file) {
            $files[] = $this->changedFile($file, FileStage::Unstaged);
        }

        foreach ($workingCopy->getUntrackedFiles() as $path) {
            $files[] = new ChangedFile($path, FileStatus::Untracked, FileStage::Untracked);
        }

        return $files;
    }

    /**
     * Reads the commits ahead of the upstream and the files they touch.
     *
     * Wrapped in its own try/catch: without a configured upstream (`@{u}` missing),
     * git throws, and that must not break the branch/commit lookup.
     *
     * @return array{0: bool, 1: list<UnpushedCommit>, 2: list<ChangedFile>}
     */
    private function collectUnpushed(Repository $repository): array
    {
        try {
            // Throws when no upstream is configured (repository in debug mode by default).
            $repository->run('rev-parse', ['--verify', '--quiet', '@{u}']);

            $format = implode(self::FIELD_SEPARATOR, ['%H', '%s', '%an', '%aI']);
            $commits = $this->parseUnpushedCommits(
                $repository->run('log', ['--format=' . $format, '@{u}..HEAD']),
            );

            $files = [];
            foreach ($this->diffFiles($repository, '@{u}..HEAD') as $file) {
                $files[] = $this->changedFile($file, FileStage::Committed);
            }

            return [true, $commits, $files];
        } catch (\Throwable) {
            // No upstream, no remote, or detached HEAD: local degradation.
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
     * Reproduces Repository::getDiff() without instantiating RevisionList (which triggers a
     * deprecation): Diff::parse() is public and only needs the raw `git diff` output.
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

    private function changedFile(File $file, FileStage $stage): ChangedFile
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

    private function statusOf(File $file): FileStatus
    {
        return match (true) {
            $file->isRename() => FileStatus::Renamed,
            $file->isCreation() => FileStatus::Added,
            $file->isDeletion() => FileStatus::Deleted,
            default => FileStatus::Modified,
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
