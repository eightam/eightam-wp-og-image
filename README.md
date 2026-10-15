# 8am OG Image

A share image for every page of a WordPress site — the picture a link shows on WhatsApp, Facebook, LinkedIn, Slack or X — drawn on the server with GD from one of four templates, in the colours and typefaces of the site's theme.

- **Reads the brand from the theme**: Blocksy's Customizer palette and typography, or a theme.json theme's global styles, and the site's logo.
- **Four templates** — Spotlight, Paper, Full bleed, Brand colour — chosen in **Settings → Share images**, with a live preview; colours, typefaces, logo and what the card says can all be changed there.
- **Kept up to date by itself**: a card's file name is a hash of everything on it, so a new title, image or setting draws it again; the old file is deleted. "Delete all share images" starts afresh.
- **Typefaces by name**: the theme's own files, or a static cut from Google Fonts fetched once by the server, kerned for GD in plain PHP. Inter is bundled as the fallback.
- Prints the Open Graph and Twitter tags, unless an SEO plugin does.
- An editor can change a post's card, or replace it with an image, in the editor's **Share image** panel.

Requires WordPress 6.7 and PHP 8.1 with GD and FreeType. See [readme.txt](readme.txt) for the filters and the WP-CLI commands.

The bundled Inter is © The Inter Project Authors, under the SIL Open Font License 1.1 (`assets/fonts/OFL.txt`).
