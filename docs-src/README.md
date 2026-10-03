# Fuse docs

Built with [Papyrus](https://github.com/milon/papyrus) 1.5.

**Live:** [oss.milon.im/fuse](https://oss.milon.im/fuse/)  
**Origin:** [milon.github.io/fuse](https://milon.github.io/fuse/)

## Build

```shell
docs-src/bin/build-site
php papyrus.phar serve -d docs-src -e docs
# open http://127.0.0.1:8000/fuse/
```

Mermaid needs `mmdc` on `PATH`. CI deploys GitHub Pages on push; [milon/oss](https://github.com/milon/oss) Worker fronts `oss.milon.im`.
