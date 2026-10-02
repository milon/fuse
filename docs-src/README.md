# Fuse docs

The site is built with [Papyrus](https://github.com/milon/papyrus) 1.5 from the Markdown in `content/`.

## Build

From the repository root:

```shell
docs-src/bin/build-site
```

The script looks for `papyrus.phar` in the repo root, then `vendor/bin/papyrus`, then `PAPYRUS_BIN`. It copies `art/banner.svg` into `docs-src/assets/` and writes the site to `docs/milon-fuse-site/`.

```shell
PAPYRUS_BIN="php papyrus.phar" docs-src/bin/build-site
```

Mermaid diagrams need the Mermaid CLI (`mmdc`) on `PATH`.

## Preview

```shell
php papyrus.phar serve -d docs-src
```

## Pages

GitHub Actions builds the site on every push and pull request. Pushes to `master` deploy to GitHub Pages. The custom domain in `papyrus.yml` is `fuse.milon.im`.
