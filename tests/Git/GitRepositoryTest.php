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
        file_put_contents($this->dir . '/file.txt', "changed\n");

        $info = (new GitRepository($this->dir))->read();

        self::assertNotNull($info);
        self::assertTrue($info->isDirty);
    }

    public function testListsWorkingTreeFilesWithStatus(): void
    {
        $this->initRepositoryWithCommit();
        // Modified but not staged.
        file_put_contents($this->dir . '/file.txt', "changed\n");
        // New staged file.
        file_put_contents($this->dir . '/added.txt', "new\n");
        $this->git('add', 'added.txt');
        // Untracked file.
        file_put_contents($this->dir . '/untracked.txt', "loose\n");

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

        // Two local commits ahead of the upstream.
        file_put_contents($this->dir . '/feature.txt', "feature\n");
        $this->git('add', 'feature.txt');
        $this->git('commit', '-m', 'feature A');
        file_put_contents($this->dir . '/file.txt', "changed\n");
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

    public function testGraphCommitsOnLinearRepositoryWithoutUpstream(): void
    {
        $this->initRepositoryWithCommit();
        file_put_contents($this->dir . '/second.txt', "second\n");
        $this->git('add', 'second.txt');
        $this->git('commit', '-m', 'second');

        $info = (new GitRepository($this->dir))->read();

        self::assertNotNull($info);
        self::assertNull($info->upstreamRef);
        self::assertCount(2, $info->graphCommits);

        [$head, $root] = $info->graphCommits;

        self::assertSame('second', $head->subject);
        self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $head->hash);
        self::assertMatchesRegularExpression('/^[0-9a-f]{7}$/', $head->shortHash);
        self::assertSame([$root->hash], $head->parentHashes);
        self::assertTrue($head->isHead);
        self::assertFalse($head->isUpstream);
        self::assertFalse($head->isPushed);
        self::assertNotSame('', $head->author);
        self::assertInstanceOf(\DateTimeImmutable::class, $head->date);

        self::assertSame('init', $root->subject);
        self::assertSame([], $root->parentHashes);
        self::assertFalse($root->isHead);
        self::assertFalse($root->isPushed);
    }

    public function testGraphCommitsWithDivergedUpstream(): void
    {
        $this->initRepositoryWithCommit();
        $this->configureUpstream();

        // One commit pushed to the upstream…
        file_put_contents($this->dir . '/remote.txt', "remote\n");
        $this->git('add', 'remote.txt');
        $this->git('commit', '-m', 'remote work');
        $this->git('push', 'origin', 'main');

        // …then the local branch is rewound and diverges with its own commit.
        $this->git('reset', '--hard', 'HEAD~1');
        file_put_contents($this->dir . '/local.txt', "local\n");
        $this->git('add', 'local.txt');
        $this->git('commit', '-m', 'local work');

        $info = (new GitRepository($this->dir))->read();

        self::assertNotNull($info);
        self::assertSame('origin/main', $info->upstreamRef);
        self::assertCount(3, $info->graphCommits);

        $bySubject = [];
        foreach ($info->graphCommits as $commit) {
            $bySubject[$commit->subject] = $commit;
        }

        $local = $bySubject['local work'] ?? null;
        $remote = $bySubject['remote work'] ?? null;
        $root = $bySubject['init'] ?? null;

        self::assertNotNull($local);
        self::assertTrue($local->isHead);
        self::assertFalse($local->isUpstream);
        self::assertFalse($local->isPushed);

        self::assertNotNull($remote);
        self::assertFalse($remote->isHead);
        self::assertTrue($remote->isUpstream);
        self::assertTrue($remote->isPushed);

        self::assertNotNull($root);
        self::assertFalse($root->isHead);
        self::assertFalse($root->isUpstream);
        self::assertTrue($root->isPushed);

        // Both diverged commits fork from the root.
        self::assertSame([$root->hash], $local->parentHashes);
        self::assertSame([$root->hash], $remote->parentHashes);
    }

    public function testGraphSurvivesAFileNamedHead(): void
    {
        $this->initRepositoryWithCommit();
        file_put_contents($this->dir . '/HEAD', "decoy\n");

        // The ambiguity between the HEAD revision and the HEAD file only arises
        // when git runs from inside the working tree — the common case when the
        // profiled application is started from the project root.
        $cwd = getcwd();
        chdir($this->dir);

        try {
            $info = (new GitRepository($this->dir))->read();
        } finally {
            chdir((string) $cwd);
        }

        self::assertNotNull($info);
        self::assertCount(1, $info->graphCommits);
    }

    public function testGraphSurvivesASubjectContainingTheFieldSeparator(): void
    {
        $this->initRepositoryWithCommit();
        file_put_contents($this->dir . '/weird.txt', "weird\n");
        $this->git('add', 'weird.txt');
        $this->git('commit', '-m', "weird\x1fsubject");

        $info = (new GitRepository($this->dir))->read();

        self::assertNotNull($info);
        self::assertCount(2, $info->graphCommits);
        self::assertSame("weird\x1fsubject", $info->graphCommits[0]->subject);
        self::assertSame('init', $info->graphCommits[1]->subject);
    }

    public function testGraphCommitsAreLimited(): void
    {
        $this->initRepositoryWithCommit();
        file_put_contents($this->dir . '/file.txt', "v2\n");
        $this->git('commit', '-am', 'v2');
        file_put_contents($this->dir . '/file.txt', "v3\n");
        $this->git('commit', '-am', 'v3');

        $info = (new GitRepository($this->dir, graphCommitLimit: 2))->read();

        self::assertNotNull($info);
        self::assertCount(2, $info->graphCommits);
        self::assertSame('v3', $info->graphCommits[0]->subject);
        self::assertSame('v2', $info->graphCommits[1]->subject);
    }

    private function initRepositoryWithCommit(): void
    {
        $this->git('init', '-b', 'main');
        $this->git('config', 'user.email', 'test@example.com');
        $this->git('config', 'user.name', 'Test');
        file_put_contents($this->dir . '/file.txt', "hello\n");
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
