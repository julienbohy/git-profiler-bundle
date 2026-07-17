# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/julienbohy/git-profiler-bundle/compare/0.1.2...HEAD
[0.1.2]: https://github.com/julienbohy/git-profiler-bundle/compare/0.1.1...0.1.2
[0.1.1]: https://github.com/julienbohy/git-profiler-bundle/releases/tag/0.1.1
