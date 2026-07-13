# GitProfilerBundle

Symfony bundle that exposes the Git state of the current repository — **branch**, **short commit**,
**modified files** and **unpushed commits** — in a dedicated **Web Profiler** panel.

> 🚧 Work in progress — not published on Packagist yet.

## Requirements

- PHP **8.3+**
- Symfony **6.4**, **7.x** or **8.x**
- The `git` binary available in the runtime environment
- Git reading relies on [`gitonomy/gitlib`](https://github.com/gitonomy/gitlib)

## Installation

Until the bundle is on Packagist, consume it via a `path` Composer repository.

In the application `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../GitProfilerBundle",
            "options": { "symlink": true }
        }
    ]
}
```

Then:

```bash
composer require --dev julienbohy/git-profiler-bundle:@dev
```

> Once published: `composer require --dev julienbohy/git-profiler-bundle`.

### Registering the bundle

With Symfony Flex the bundle is registered automatically. Otherwise, in `config/bundles.php`:

```php
return [
    // ...
    JulienBohy\GitProfilerBundle\GitProfilerBundle::class => ['dev' => true, 'test' => true],
];
```

The bundle is only useful in a development environment (Web Profiler): `dev` (and `test`) are enough.

## Usage

No configuration required. As soon as the profiler is active, a **Git** panel appears in the debug
bar and in the profiler.

In the (compact) **toolbar** you get the current **branch** followed by **two counters**: the number
of **locally modified files** (✎) and the number of **unpushed commits** (↑).

The **detailed panel** additionally shows:

- the **short commit** of `HEAD`;
- the **list of uncommitted working-tree files** (staged, unstaged, untracked) with their status
  (added, modified, deleted, renamed…);
- the **list of local commits ahead of the remote** (unpushed) — short hash, message, author, date —
  **together with the list of files they touch**.

Unpushed-commit detection:

- it is based on the **upstream** branch (`@{u}`, e.g. `origin/main`); with no upstream configured,
  the section says so and the counters show `–`;
- the list of unpushed files is the **net diff** `@{u}..HEAD` (a file created then deleted within the
  range therefore does not appear).

If the directory is not a Git repository (or if `git` is unavailable), the panel states it cleanly —
no exception is thrown.

## Architecture

- `Git\GitRepositoryInterface` — port: `read(): ?GitInfo` (`null` = degradation).
- `Git\GitRepository` — adapter relying on `gitonomy/gitlib`.
- `Git\GitInfo` — immutable value object (`branch`, `shortCommit`, `isDirty`, `workingFiles`,
  `hasUpstream`, `unpushedCommits`, `unpushedFiles`).
- `Git\ChangedFile` — immutable value object of a changed file (`path`, `status`, `stage`,
  `oldPath`, `additions`, `deletions`).
- `Git\FileStatus`, `Git\FileStage` — backed-string enums for a file's status (added, modified,
  deleted, renamed, untracked) and location (staged, unstaged, untracked, committed), with their
  display labels (`label()`).
- `Git\UnpushedCommit` — immutable value object of an unpushed commit (`shortHash`, `subject`,
  `author`, `date`).
- `DataCollector\GitDataCollector` — logic-free collector, delegates to the port, flattens the value
  objects into scalars (profiler-serializable) and exposes the data to the
  `@GitProfiler/Collector/git.html.twig` template.

## Tests

```bash
composer install
vendor/bin/phpunit
```

## License

MIT © 2026 Julien Bohy
