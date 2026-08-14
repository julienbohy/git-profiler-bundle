# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- The release workflow now promotes the CHANGELOG through a short-lived squash-merged pull
  request instead of pushing to `main` directly, which branch protection (pull request required,
  admins included) rejects.

## [0.2.0] - 2026-08-14

### Added

- Commit graph in the profiler panel: the recent commits of `HEAD` and its upstream drawn as an
  SVG graph (lanes, merge and divergence curves), with `HEAD`/upstream badges and hollow dots for
  commits not pushed to the upstream yet.

### Changed

- Exclude development files (`tests/`, `docs/`, `.github/`, and other repository-only files) from the Composer dist archive via `.gitattributes`, so installs pull only the runtime code.

### Fixed

- A commit subject containing the raw field separator byte (0x1F) no longer empties the whole
  unpushed-commits list: the subject is now the last parsed field (kept whole by the explode
  limit) and malformed lines are skipped individually, as already done for the commit graph.

## [0.1.2] - 2026-07-17

### Changed

- Document Packagist installation in the README.

## [0.1.1] - 2026-07-17

### Added

- Web Profiler panel exposing the Git state of the current repository.
- Toolbar showing the current branch with counters for locally modified files and unpushed commits.
- Detailed working-tree changes (staged, unstaged, untracked) with their status.
- List of commits ahead of the upstream branch, with the files they touch.
- Graceful degradation when the directory is not a Git repository or `git` is unavailable.

[Unreleased]: https://github.com/julienbohy/git-profiler-bundle/compare/0.2.0...HEAD
[0.2.0]: https://github.com/julienbohy/git-profiler-bundle/compare/0.1.2...0.2.0
[0.1.2]: https://github.com/julienbohy/git-profiler-bundle/compare/0.1.1...0.1.2
[0.1.1]: https://github.com/julienbohy/git-profiler-bundle/releases/tag/0.1.1
