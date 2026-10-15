# 8am OG Image

A share image for every page of a WordPress site — the picture a link shows on WhatsApp, Facebook, LinkedIn, Slack or X — drawn on the server with GD from one of four templates, in the colours and typefaces of the site's theme.

- **Reads the brand from the theme**: Blocksy's Customizer palette and typography, or a theme.json theme's global styles, and the site's logo.
- **Four templates** — Spotlight, Paper, Full bleed, Brand colour — chosen in **Settings → Share images**, with a live preview; colours, typefaces, logo and what the card says can all be changed there, and **variants** give a page and its sub-pages, or a kind of post, a template and logo of their own.
- **Blocksy Pro**: custom fonts (WOFF2 included, unpacked once in the admin's browser) and the logo of a page's own header.
- **SVG logos** drawn exactly, by the admin's browser, once.
- **Kept up to date by itself**: a card's file name is a hash of everything on it, so a new title, image or setting draws it again; the old file is deleted. "Delete all share images" starts afresh.
- **Typefaces by name**: the theme's own files, or a static cut from Google Fonts fetched once by the server, kerned for GD in plain PHP. Plus Jakarta Sans is bundled as the fallback.
- Prints the Open Graph and Twitter tags, unless an SEO plugin does; with SEOPress, puts the card into SEOPress's own og:image and twitter:image.
- An editor can change a post's card, or replace it with an image, in the editor's **Share image** panel.

Requires WordPress 6.7 and PHP 8.1 with GD and FreeType. See [readme.txt](readme.txt) for the filters and the WP-CLI commands.

## Install and update

Download `eightam-og-image.zip` from the [latest release](https://github.com/eightam/eightam-wp-og-image/releases/latest) and upload it under Plugins → Add New. From then on WordPress offers each new release on the Plugins screen, and installs it automatically if auto-updates are turned on for the plugin.

## Releasing

Raise the version in all three places — `Version:` in the header and `VERSION` in `eightam-og-image.php`, `Stable tag:` in `readme.txt` — and add a changelog entry under `== Changelog ==` in `readme.txt`. When that lands on `main`, the [Release workflow](.github/workflows/release.yml) checks the code, builds `eightam-og-image.zip` and publishes it as release `v{version}` with the changelog entry as its notes. A push that does not raise the version releases nothing; versions that disagree fail the run.

## License

GPL-2.0-or-later, as WordPress itself; see [LICENSE](LICENSE). The vendored WOFF2 decoder (`assets/vendor/woff2/`) is wawoff2, MIT. The bundled Plus Jakarta Sans is © The Plus Jakarta Sans Project Authors, under the SIL Open Font License 1.1 (`assets/fonts/OFL.txt`).
