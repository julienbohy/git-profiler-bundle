<?php

declare(strict_types=1);

namespace JulienBohy\GitProfilerBundle\Tests\Git;

use JulienBohy\GitProfilerBundle\Git\GitRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class GitRepositoryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/gpb_' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->dir);
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
    }

    public function testDetectsDirtyWorkingTree(): void
    {
        $this->initRepositoryWithCommit();
        file_put_contents($this->dir . '/file.txt', "modifié\n");

        $info = (new GitRepository($this->dir))->read();

        self::assertNotNull($info);
        self::assertTrue($info->isDirty);
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
