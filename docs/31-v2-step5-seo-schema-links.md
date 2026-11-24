# V2 Step 5 — SEO metadata, schema registry & internal link engine

Status: implemented locally, not yet committed/released.

## 1. SEO metadata engine

File: `includes/class-seo-metadata-engine.php`

- `build()` menghasilkan:
  - `seo_title`, `description`, `h1`, `slug`
  - `canonical` self-referencing dari permalink
  - `robots` (`noindex,follow` untuk experiment)
  - `language` + `html_lang`
  - Open Graph + Twitter card
  - `image_alt`
  - `sitemap_eligible`
  - `warnings` + `blockers`
- Aturan:
  - title dipotong 60 karakter, description 155
  - H1 harus tepat satu bila HTML diberikan
  - canonical host harus sama dengan home_url
  - halaman noindex tidak boleh masuk sitemap
- `store()` menyimpan `_lpgen_v2_seo_meta`.
- `apply_yoast()` hanya mengisi field Yoast; **tidak** membuat canonical/schema kedua.

`Publisher::apply_seo_meta()` sekarang menyimpan metadata hasil engine.

## 2. Schema registry

File: `includes/class-schema-registry.php`

- `graph()` menghasilkan node dengan `@id` stabil.
- Yoast adalah authority: plugin hanya meng-inject lewat filter `wpseo_schema_graph`.
- `inject_yoast()` melakukan dedup by `@id`, sehingga tidak menggandakan Organization/WebSite.
- `fallback_output()` hanya jalan bila tidak ada Yoast/Rank Math/AIOSEO/SEOPress.

Node per tipe:

| Tipe | Node | Catatan |
|---|---|---|
| service | `Service`, `WebPage` | `areaServed` dari kota nyata; `Offer` hanya bila harga + status tersedia |
| product | `Product` / `SoftwareApplication`, `WebPage` | `offers` hanya bila harga + status valid |
| amazon_affiliate | `Article`, `WebPage` | **Tanpa** `aggregateRating`, `Review` rating, atau `offers` rekaan |

`citation` diambil dari `amazon_source_reference`.

## 3. Internal link engine

File: `includes/class-internal-link-engine.php`

- `nodes()` mengumpulkan `lpgen_item` + campaign sebagai node graph.
- `edges()` menghasilkan relasi:
  - `PARENT_HUB` dari path bertingkat
  - `SUPPORTING_GUIDE` untuk TOFU/MOFU
  - `RELATED_GUIDE` untuk tag/lainnya
- `suggest()` memilih urutan berdasarkan:
  1. prioritas `link_strategy()` tipe LP
  2. kecocokan kota
  3. bahasa (EN marker)
  4. status approved/published
  5. exclude item saat regenerate
- `suggest_for_lp()` dipakai `SeoStandards::refresh_related()`, menggantikan daftar “6 artikel pertama”.

## 4. Hooks baru

Di `includes/class-plugin.php`:

```php
add_filter( 'wpseo_schema_graph', array( SchemaRegistry::class, 'inject_yoast' ), 10, 2 );
add_action( 'wp_head', array( SchemaRegistry::class, 'maybe_output_fallback' ), 5 );
```

Tidak ada canonical tag atau JSON-LD graph duplikat pada situs dengan Yoast.

## 5. Tests

```text
php tests\run.php → 959 passed, 0 failed
node tests\test-admin-runner.js → PASS
php tests\smoke-bootstrap.php → PASS (42 hook groups)
php -l → No syntax errors
```

Regression:
- title/description truncation
- noindex → sitemap false
- H1 tunggal
- schema Service + areaServed
- schema affiliate tanpa rating/harga
- inject Yoast tidak menambah node post non-LP
- link ranking mengutamakan kota yang sama
- related lama tetap kompatibel

## 6. Catatan

Belum ada admin UI untuk approve/reorder saran link (Step 9/16). Belum ada quality score, experiment mode, atau hard blocker publish (Step 6–7).
