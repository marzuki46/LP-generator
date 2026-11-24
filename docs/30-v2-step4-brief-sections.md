# V2 Step 4 — Brief generator & section schema per tipe

Status: implemented locally, not yet committed/released.

## Field mapping

`AbstractLpTypeStrategy::resolve()` memetakan field lama ke brief baru:

| Brief field | Sumber |
|---|---|
| `contact_method` | `contact_method` → `phone` → `contact_email` → `offer_url` |
| `product_type` | `product_type` → `offer_type` |
| `description` | `description` → `angle` → `promise` |
| `target_customer` | `target_customer` → `audience` |
| `amazon_marketplace` | `amazon_marketplace` → `amazon_region` |
| `affiliate_tag` | `affiliate_tag` → `amazon_tag` |
| `source_reference` | `source_reference` → URL pertama `research_sources` |
| `original_analysis` | `original_analysis` → `angle` → `promise` |

Ini menghilangkan risiko `validate_brief()` gagal karena field baru belum ada di campaign lama.

## Section schema per tipe

- **service**: hero, overview, problems, deliverables, benefits, portfolio, process, pricing, service_area, faq, contact
- **product**: hero, what_it_does, who_for, problems, features, demo, specifications, pricing, setup_support, comparison, faq, purchase
- **amazon_affiliate**: hero, quick_recommendations, selection_method, comparison_table, individual_analysis, pros_cons, specifications, which_fits, alternatives, buying_guide, faq, final_recommendation

Sekarang tersedia lewat `LpTypeStrategy::section_schema()`.

## `LpBriefGenerator`

File: `includes/class-lp-brief-generator.php`

- `generate($context)` → brief kanonik: `lp_type`, `language`, `locale`, `keyword`, `intent`, `sections`, `schema_types`, `seo_rules`, `quality_checks`, `generated_at`.
- `validate($brief)` → memakai `validate_brief()` strategi.
- `to_prompt($brief)` → string JSON untuk disuntik ke prompt AI.
- `intent()` → `transactional` untuk service/product, `commercial` untuk affiliate bila tidak dideklarasikan.

Integrasi:
- `Generator::generate_item()` mengisi `$context['lp_brief']`.
- `Prompts::build_lp()` menyuntik `CONTENT BRIEF` ke prompt.
- Root HTML LP kini punya `data-lp-type` dan class `lpg-lpt--<type>`.

## Input campaign baru

Product:
- `product_type` (physical/digital/saas/service)
- `product_status` (available/preorder/unavailable)
- `product_specs`
- `product_warranty`
- `return_policy`

Amazon affiliate:
- `amazon_asin`
- `amazon_marketplace`
- `amazon_affiliate_tag`
- `amazon_source_reference`
- `amazon_original_analysis`

UI: **Data khusus tipe LP** di campaign editor. Tanpa ASIN/tag/sumber, brief affiliate gagal validasi dan tidak boleh dipublish.

## Tests

```text
php tests\run.php → 938 passed, 0 failed
node tests\test-admin-runner.js → PASS
php tests\smoke-bootstrap.php → PASS
php -l → No syntax errors
```

Regression:
- service brief lolos dengan phone → contact_method
- affiliate marketplace/tag diturunkan dari settings lama
- affiliate tanpa ASIN ditolak
- section schema service dipakai
- brief bisa disuntik ke prompt

## Template visual per tipe (adjustment)

Setelah review, template visual dibuat benar-benar berbeda per tipe:

- File: `includes/class-lp-type-template.php`, CSS `public/css/lp-type-templates.css`
- `LandingPage::render()` memakai `LpTypeTemplate::render()` bila campaign memiliki `lp_type` (campaign produksi selalu punya). Campaign lama tanpa key tetap memakai renderer lama agar regression tidak rusak.

Perbedaan visual:

| Tipe | Ciri |
|---|---|
| service | Hero navy, CTA konsultasi + WhatsApp, blok Contact Us gelap |
| product | Hero terang, banner produk dengan harga/status/spesifikasi, CTA checkout |
| amazon_affiliate | Disclosure Amazon Associates di atas, rekomendasi cepat 4 kartu, CTA netral “Check availability on Amazon” |

QA Playwright 390×844: ketiga tipe tidak memiliki overflow horizontal (`scrollWidth === clientWidth === 390`). H1 tetap satu dan `data-step` sesuai jumlah langkah framework.

## Catatan

Step 4 sekarang mencakup brief generator, section schema, dan template visual per tipe. Step 5 akan menambahkan SEO metadata engine, schema registry, dan internal link engine.
