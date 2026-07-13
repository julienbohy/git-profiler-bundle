<?php

declare(strict_types=1);

namespace JulienBohy\GitProfilerBundle\Tests\Git;

use JulienBohy\GitProfilerBundle\Git\ChangedFile;
use JulienBohy\GitProfilerBundle\Git\FileStage;
use JulienBohy\GitProfilerBundle\Git\FileStatus;
use JulienBohy\GitProfilerBundle\Git\GitRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class GitRepositoryTest extends TestCase
{
    private string $dir;
    private string $remoteDir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/gpb_' . bin2hex(random_bytes(6));
        $this->remoteDir = sys_get_temp_dir() . '/gpb_remote_' . bin2hex(random_bytes(6));
        mkdir($this->dir);
        mkdir($this->remoteDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->dir);
        $this->removeDirectory($this->remoteDir);
    }

    public function testReturnsNullWhenNotAGitRepository(): void
    {
        self::assertNull((new GitRepository($this->dir))->read());
    }

    public function testReturnsNullOnRepositoryWithoutCommits(): void
    {
        $this->git('init', '-b', 'main');

        self::assertNull((new GitRepository($this->dir))->read());
    }

    public function testReadsBranchAndShortCommitOnCleanRepository(): void
    {
        $this->initRepositoryWithCommit();

        $info = (new GitRepository($this->dir))->read();

        self::assertNotNull($info);
        self::assertSame('main', $info->branch);
        self::assertMatchesRegularExpression('/^[0-9a-f]{7,}$/', $info->shortCommit);
        self::assertFalse($info->isDirty);
        self::assertSame([], $info->workingFiles);
    }

    public function testDetectsDirtyWorkingTree(): void
    {
        $this->initRepositoryWithCommit();
        file_put_contents($this->dir . '/file.txt', "modifié\n");

        $info = (new GitRepository($this->dir))->read();

        self::assertNotNull($info);
        self::assertTrue($info->isDirty);
    }

    public function testListsWorkingTreeFilesWithStatus(): void
    {
        $this->initRepositoryWithCommit();
        // Modifié mais non indexé.
        file_put_contents($this->dir . '/file.txt', "modifié\n");
        // Nouveau fichier indexé.
        file_put_contents($this->dir . '/added.txt', "nouveau\n");
        $this->git('add', 'added.txt');
        // Fichier non suivi.
        file_put_contents($this->dir . '/untracked.txt', "libre\n");

        $info = (new GitRepository($this->dir))->read();

        self::assertNotNull($info);
        self::assertTrue($info->isDirty);

        $byStage = $this->indexByStagePath($info->workingFiles);

        self::assertSame(FileStatus::Added, $byStage['staged']['added.txt'] ?? null);
        self::assertSame(FileStatus::Modified, $byStage['unstaged']['file.txt'] ?? null);
        self::assertSame(FileStatus::Untracked, $byStage['untracked']['untracked.txt'] ?? null);
    }

    public function testKeepsPartiallyStagedFileInBothStages(): void
    {
        $this->initRepositoryWithCommit();
        file_put_contents($this->dir . '/file.txt', "v2\n");
        $this->git('add', 'file.txt');
        file_put_contents($this->dir . '/file.txt', "v3\n");

        $info = (new GitRepository($this->dir))->read();

        self::assertNotNull($info);
        $byStage = $this->indexByStagePath($info->workingFiles);

        self::assertSame(FileStatus::Modified, $byStage['staged']['file.txt'] ?? null);
        self::assertSame(FileStatus::Modified, $byStage['unstaged']['file.txt'] ?? null);
    }

    public function testNoUpstreamConfigured(): void
    {
        $this->initRepositoryWithCommit();

        $info = (new GitRepository($this->dir))->read();

        self::assertNotNull($info);
        self::assertFalse($info->hasUpstream);
        self::assertSame([], $info->unpushedCommits);
        self::assertSame([], $info->unpushedFiles);
    }

    public function testUpstreamWithAheadCommits(): void
    {
        $this->initRepositoryWithCommit();
        $this->configureUpstream();

        // Deux commits locaux en avance sur l'upstream.
        file_put_contents($this->dir . '/feature.txt', "feature\n");
        $this->git('add', 'feature.txt');
        $this->git('commit', '-m', 'feature A');
        file_put_contents($this->dir . '/file.txt', "changé\n");
        $this->git('commit', '-am', 'feature B');

        $info = (new GitRepository($this->dir))->read();

        self::assertNotNull($info);
        self::assertTrue($info->hasUpstream);
        self::assertCount(2, $info->unpushedCommits);

        $subjects = array_map(static fn ($c) => $c->subject, $info->unpushedCommits);
        self::assertContains('feature A', $subjects);
        self::assertContains('feature B', $subjects);

        foreach ($info->unpushedCommits as $commit) {
            self::assertMatchesRegularExpression('/^[0-9a-f]{7}$/', $commit->shortHash);
            self::assertInstanceOf(\DateTimeImmutable::class, $commit->date);
            self::assertNotSame('', $commit->author);
        }

        $paths = array_map(static fn (ChangedFile $f) => $f->path, $info->unpushedFiles);
        self::assertContains('feature.txt', $paths);
        self::assertContains('file.txt', $paths);
        foreach ($info->unpushedFiles as $file) {
            self::assertSame(FileStage::Committed, $file->stage);
        }
    }

    public function testUpstreamWithoutAheadCommits(): void
    {
        $this->initRepositoryWithCommit();
        $this->configureUpstream();

        $info = (new GitRepository($this->dir))->read();

        self::assertNotNull($info);
        self::assertTrue($info->hasUpstream);
        self::assertSame([], $info->unpushedCommits);
        self::assertSame([], $info->unpushedFiles);
    }

    private function initRepositoryWithCommit(): void
    {
        $this->git('init', '-b', 'main');
        $this->git('config', 'user.email', 'test@example.com');
        $this->git('config', 'user.name', 'Test');
        file_put_contents($this->dir . '/file.txt', "bonjour\n");
        $this->git('add', '.');
        $this->git('commit', '-m', 'init');
    }

    private function configureUpstream(): void
    {
        (new Process(['git', 'init', '--bare', '-b', 'main', $this->remoteDir]))->mustRun();
        $this->git('remote', 'add', 'origin', $this->remoteDir);
        $this->git('push', '-u', 'origin', 'main');
    }

    /**
     * @param list<ChangedFile> $files
     *
     * @return array<string, array<string, FileStatus>> [stage => [path => status]]
     */
    private function indexByStagePath(array $files): array
    {
        $index = [];
        foreach ($files as $file) {
            $index[$file->stage->value][$file->path] = $file->status;
        }

        return $index;
    }

    private function git(string ...$args): void
    {
        (new Process(['git', ...$args], $this->dir))->mustRun();
    }

    private function removeDirectory(string $directory): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
