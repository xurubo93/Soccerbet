# Changelog

All notable changes to this module are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.0.1] - 2026-06-10

### Fixed

- **LiveController:** Resolved PHP notice "Only variables should be passed by
  reference" in the `/liveJson` endpoint. `Renderer::renderRoot()` expects the
  render array by reference; the render arrays are now assigned to local
  variables first.

### Upgrade

Pure bugfix release. No schema changes, no `drush updb` required.
Run `lando drush cr` after updating.

## [1.0.0] - 2026-04-11

First stable release after the beta cycle.
