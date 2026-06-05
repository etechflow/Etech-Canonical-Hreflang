# Etechflow_CanonicalHreflang

**Canonical URLs + hreflang tags** for Magento 2 — de-duplicates the URL variants that leak SEO equity and serves the right store view per locale. Part of the **Etechflow SEO Suite**.

## Why

Magento generates the same content under many URLs — a product reachable under several category paths, category pages with `?colour=red&price=10-20`, `?p=2` pagination, and tracking-param variants. Without a clean canonical, Google splits ranking signals across all of them. This module emits one authoritative canonical per page, and for multi-store catalogues, hreflang links so each locale's customers land on the right store view.

## Features

**Canonical**
- Product / category / CMS pages (each toggleable).
- **Category-free product canonical** — a product in 5 categories still has one canonical.
- **Strips query parameters** — `?filter`, `?sort`, `?utm_*` all canonicalise to the clean URL.
- **Pagination control** — self-referencing (`?p=2` canonicalises to itself, Google-recommended) or consolidate-to-page-1.
- **De-dupes** — removes any existing canonical (e.g. Magento core's) so you never emit two.

**Hreflang**
- `rel="alternate"` links across every active store view.
- Each store's code auto-derived from its **Locale** (`en_GB` → `en-gb`), or overridden per store (`1:en-gb`).
- Optional `x-default`.

## How it works

A single plugin on `Magento\Framework\View\Result\Page::renderResult` resolves the current entity and adds the `<link>` tags via **PageConfig remote assets** — so they render in any theme, including Hyvä, with no template changes. `Service\CanonicalResolver` and `Service\HreflangResolver` compute the URLs.

## Install

```bash
composer require etechflow/module-canonical-hreflang
bin/magento module:enable Etechflow_CanonicalHreflang
bin/magento setup:upgrade
bin/magento setup:di:compile     # production
```

## Configure

**Stores → Configuration → Etechflow → Canonical & Hreflang**: master *Enable*, then the canonical toggles (product/category/CMS, strip-query, pagination mode) and hreflang (enable, x-default store, per-store overrides).

> Tip: turn off Magento's own canonical (Catalog → Catalog → Search Engine Optimization) — this module dedupes it anyway, but disabling avoids the wasted work.

## License

Proprietary — © eTechFlow.
