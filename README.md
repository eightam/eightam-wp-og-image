# 8am OG Image

A share image for every page of a WordPress site — the picture a link shows on WhatsApp, Facebook, LinkedIn, Slack or X — drawn on the server with GD from one of eight templates, in the colours and typefaces of the site's theme.

- **Reads the brand from the theme**: Blocksy's Customizer palette and typography, or a theme.json theme's global styles, and the site's logo.
- **Eight templates** — classic: Spotlight, Paper, Full bleed, Brand colour; bento: Bento, Mosaic, Magazine, Banner — chosen in **Settings → Share images**, with a live preview; colours, typefaces, logo and what the card says can all be changed there, and **variants** give a page and its sub-pages, or a kind of post, a template and logo of their own.
- **Bento tiles**: the title, the photograph, the logo and one or two facts (date, author, category, reading time, address, or an ACF field) on tiles with rounded corners, in a dark, light or brand-colour tone, with the gap and corner radius on sliders. A tile with nothing to show gets the mark or a pattern of squares.
- **Blocksy Pro**: custom fonts (WOFF2 included, unpacked once in the admin's browser) and the logo of a page's own header.
- **SVG logos** drawn exactly, by the admin's browser, once.
- **Kept up to date by itself**: a card's file name is a hash of everything on it, so a new title, image or setting draws it again; the old file is deleted. "Delete all share images" starts afresh.
- **Typefaces by name**: the theme's own files, or a static cut from Google Fonts fetched once by the server, kerned for GD in plain PHP. Plus Jakarta Sans is bundled as the fallback.
- Prints the Open Graph and Twitter tags, unless an SEO plugin does; with SEOPress, puts the card into SEOPress's own og:image and twitter:image.
- An editor can change a post's card, or replace it with an image, in the editor's **Share image** panel.

Requires WordPress 6.7 and PHP 8.1 with GD and FreeType. See [readme.txt](readme.txt) for the filters and the WP-CLI commands.

## Install and update

Install `https://eightam-wp.b-cdn.net/eightam-og-image/eightam-og-image.zip` (always the newest version) under Plugins → Add New, or through the 8am Toolkit. From then on WordPress offers each new version on the Plugins screen, and installs it automatically if auto-updates are turned on. The updater (`includes/updater.php`) reads `https://eightam-wp.b-cdn.net/eightam-og-image/latest.json`, at most every 12 hours.

Installs on 1.4.1 or older update from GitHub releases. Each release still carries `eightam-og-image.zip`, so they update once from GitHub to the first version with the CDN updater, and from the CDN after that.

## Releasing

Never edit a version number by hand: [release-please](https://github.com/googleapis/release-please) owns them. Squash-merge every PR with a Conventional Commit title, which becomes the changelog line:

- `fix:` patch, `feat:` minor, `feat!:` or a `BREAKING CHANGE:` footer major;
- `chore:` `docs:` `ci:` `refactor:` `test:` release nothing.

release-please keeps a release PR open that bumps `Version:` and `VERSION` in `eightam-og-image.php` and writes `CHANGELOG.md`. Merging it tags `vX.Y.Z` and creates the GitHub Release; the [Release workflow](.github/workflows/release.yml) then builds the zip with `git archive` (exclusions in `.gitattributes`), attaches it to the release, and uploads it with `latest.json` (`scripts/build-manifest.mjs`) to Bunny Storage `eightam-wp/eightam-og-image/`. Running the workflow by hand republishes the version on `main` to Bunny.

Tests: `php tests/run.php`.

## License

GPL-2.0-or-later, as WordPress itself; see [LICENSE](LICENSE). The vendored WOFF2 decoder (`assets/vendor/woff2/`) is wawoff2, MIT. The bundled Plus Jakarta Sans is © The Plus Jakarta Sans Project Authors, under the SIL Open Font License 1.1 (`assets/fonts/OFL.txt`).
