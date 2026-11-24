#!/usr/bin/env node
// Writes the update manifest (latest.json) read by the plugin's updater and the
// 8am Toolkit to stdout. Sources: the main file's header and VERSION constant,
// readme.txt (Tested up to, when the header lacks it), CHANGELOG.md (release-please).
// --print-version: validate and print the version only.
// Env: CDN_BASE (default https://eightam-wp.b-cdn.net/<slug>)
import { readFileSync, existsSync } from 'node:fs'

const SLUG = 'eightam-og-image'
const CDN_BASE = (process.env.CDN_BASE || `https://eightam-wp.b-cdn.net/${SLUG}`).replace(/\/+$/, '')

const php = readFileSync(`${SLUG}.php`, 'utf8')
const header = (key) => php.match(new RegExp(`^\\s*\\*\\s*${key}:\\s*(.+)$`, 'm'))?.[1].trim()
const readme = existsSync('readme.txt') ? readFileSync('readme.txt', 'utf8') : ''
const readmeHeader = (key) => readme.match(new RegExp(`^${key}:\\s*(.+)$`, 'm'))?.[1].trim()
const version = header('Version')
const constVersion = php.match(/^const VERSION = '([^']+)';/m)?.[1]

if (!version || version !== constVersion) {
  console.error(`Version mismatch: header=${version} VERSION=${constVersion}`)
  process.exit(1)
}
if (process.argv.includes('--print-version')) {
  console.log(version)
  process.exit(0)
}

// CHANGELOG.md -> HTML: last 10 releases, headings + bullets, links/commit refs stripped.
const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
const clean = (s) =>
  s
    .replace(/\s*\(\[[0-9a-f]{7,}\]\([^)]*\)\)/g, '')
    .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
    .replace(/\*\*([^*]+)\*\*/g, '$1')
    .replace(/`([^`]+)`/g, '$1')
    .trim()
let changelog = ''
if (existsSync('CHANGELOG.md')) {
  const releases = readFileSync('CHANGELOG.md', 'utf8').split(/^## /m).slice(1, 11)
  changelog = releases
    .map((block) => {
      const [head, ...lines] = block.split('\n')
      const v = head.match(/\d+\.\d+\.\d+[^\]\s)]*/)?.[0] ?? clean(head)
      // A bullet's wrapped continuation lines (indented, not a new bullet) belong to it.
      const bullets = []
      for (const l of lines) {
        if (/^\s*[*-] /.test(l)) bullets.push(l.replace(/^\s*[*-] /, ''))
        else if (bullets.length && /^\s{2,}\S/.test(l)) bullets[bullets.length - 1] += ' ' + l.trim()
      }
      const items = bullets.map((b) => `<li>${esc(clean(b))}</li>`)
      return `<h4>${esc(v)}</h4>${items.length ? `<ul>${items.join('')}</ul>` : ''}`
    })
    .join('')
}

const manifest = {
  name: header('Plugin Name'),
  slug: SLUG,
  version,
  homepage: header('Plugin URI') ?? '',
  download_url: `${CDN_BASE}/${SLUG}-${version}.zip`,
  requires: header('Requires at least') ?? '',
  requires_php: header('Requires PHP') ?? '',
  tested: header('Tested up to') ?? readmeHeader('Tested up to') ?? '',
  last_updated: new Date().toISOString(),
  description: header('Description') ?? '',
  changelog,
}
process.stdout.write(JSON.stringify(manifest, null, 2) + '\n')
