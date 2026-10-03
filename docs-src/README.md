# Fuse docs

The site is built with [Papyrus](https://github.com/milon/papyrus) 1.5 from the Markdown in `content/`.

Live URL: [oss.milon.im/fuse](https://oss.milon.im/fuse/) (deployed from [milon/oss](https://github.com/milon/oss)).

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
php papyrus.phar serve -d docs-src -e docs
# open http://127.0.0.1:8000/fuse/
```

## CI

GitHub Actions builds the site on docs changes (verify only). Production deploy is the `milon/oss` aggregator.
