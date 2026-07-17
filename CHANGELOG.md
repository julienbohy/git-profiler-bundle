# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Web Profiler panel exposing the Git state of the current repository.
- Toolbar showing the current branch with counters for locally modified files and unpushed commits.
- Detailed working-tree changes (staged, unstaged, untracked) with their status.
- List of commits ahead of the upstream branch, with the files they touch.
- Graceful degradation when the directory is not a Git repository or `git` is unavailable.

[Unreleased]: https://github.com/julienbohy/git-profiler-bundle/commits/main
