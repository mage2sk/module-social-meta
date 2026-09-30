# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.3] - 2026-09-30

### Added
- The `og_title`, `og_description` and `og_image` product and category attributes are now used for `og:title`, `og:description` and `og:image` (and the matching Twitter Card tags) when they hold a value for the current store view. Empty values fall back to the previous sources. `og_image` accepts an absolute URL or a path relative to the media folder.
- Unit tests for the Open Graph resolver.

### Fixed
- `product:availability` now outputs `in stock` or `out of stock` as defined by the Open Graph product specification instead of `instock` and `oos`.
- The last image fallback no longer points at a placeholder file that does not exist. The configured base image placeholder is used when set; otherwise `og:image` is omitted.
