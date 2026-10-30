# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.5] - 2026-10-30

### Changed
- Released under the MIT License: this module is free and open-source software.

## [1.1.4] - 2026-10-04

### Added
- `og:image:width`, `og:image:height` and `og:image:type` are now output when the share image is a file in the store's media folder, so social networks can render the preview without downloading the image first.
- `og:image:alt` and `twitter:image:alt` are now output with the share title whenever an image is present.
- The OG Title, OG Description and OG Image URL fields are now shown on the category edit form under Search Engine Optimization, with a Use Default Value option on store views. The attributes were created by the data patch but could not be edited in the admin.
- Help text for the OG fields on the product and category edit forms and for the Default OG Image setting. On the product form the OG fields now follow Meta Description in the order Title, Description, Image.

### Changed
- `twitter:card` is now `summary` on pages that have no share image (for example the home page or search results without a Default OG Image), because a large image card without an image is not valid.
- Twitter Card Type and Twitter Site Handle are shown only when Twitter Card tags are enabled, and the article settings only when Open Graph tags are enabled. The Twitter Site Handle field now rejects HTML tags.
