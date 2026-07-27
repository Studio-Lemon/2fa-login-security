# Asset Sources

This directory contains editable source counterparts for bundled plugin assets.

## CSS

- `src/css/*.css` maps directly to `css/*.css`.

## JavaScript

- `src/js/*.js` maps directly to `js/*.js`.

## Build Commands

- `npm run watch` builds in development mode and watches for source changes.
- `npm run dev` builds once in development mode.
- `npm run build` builds once in production mode.

Cache busting is handled by WordPress enqueue versioning (`$ver` / 4th parameter), not by filename suffixes.
