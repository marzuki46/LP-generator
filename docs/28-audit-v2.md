# Audit V2 — Universal SEO Landing Page Generator

Status: **Phase 1 audit + Phase 2 proposed architecture**. Belum ada kode V2 yang diubah pada dokumen ini.

## Bukti yang dipakai

- Source primer plugin di `D:\Program Marzuki\Plugin\LP Generator\lp-generator`
- Knowledge graph codebase-memory (index ulang): 1.037 node, 3.143 edge, 67 file
- Live HTML: `https://cahsolo.my.id/jasa/website/bali/` dan `/jasa/website/solo/`
- Test lokal terakhir: `php tests/run.php` → **920 passed, 0 failed** (v1.9.3)

## 1. Struktur & entry point

- Entry: `lp-generator.php` → konstanta `LPG_VERSION`, `LPG_PATH`, `LPG_DB_VERSION`, lalu `require_once` 30+ class.
- Bootstrap: `includes/class-plugin.php` mendaftarkan hook WP (init, admin_menu, wp_head, template_include, AJAX, updater).
- Tidak ada REST API (`register_rest_route` tidak ditemukan). Semua aksi admin memakai `admin-ajax.php`.
- Storage:
  - WordPress CPT `lpgen_campaign`, `lpgen_item`
  - Custom tables via `dbDelta`: `wp_lpg_jobs`, `wp_lpg_logs`, `wp_lpg_topic_memory`
  - Post meta untuk spec, warning, image lifecycle, SEO meta, provenance.
- Pipeline generasi: `Generator::start()` → job queue → `generate_framework` / `generate_entities` / `generate_topic` / `generate_article` / `generate_lp` → `Publisher::publish_item` → Indexer (PuSH/IndexNow).
- AI: `AiClient` ke 9Router `/v1/chat/completions`, `/v1/images/generations`, `/v1/embeddings`.
- Frontend: `LandingPage`, `AidaLayout`, `LpTemplate` (4 model visual), `LpParentPages`, CSS scoped.
- Admin: Settings 8 tab, Campaign UI, Review UI, Activity Log, JS runner.

Modul de-facto dari graph: campaign/generation cluster, admin/sanitization cluster, publish/render cluster, queue/indexer cluster, AI client cluster, prompt cluster.

## 2. Status SEO saat ini (live)

Dari HTML produksi:

- Yoast SEO **28.9** aktif dan mengelola:
  - `<title>`, meta description
  - self-referencing canonical
  - `meta robots`
  - Open Graph + Twitter card
  - `yoast-schema-graph` JSON-LD (WebPage, ImageObject, BreadcrumbList, WebSite, Organization)
- `<html lang="id">` sudah benar.
- **Tidak ada hreflang** alternate untuk EN/ID.
- Tidak ada canonical duplikat dari plugin — ini harus dipertahankan.
- Breadcrumb Yoast sudah ada; jangan membuat breadcrumb JSON-LD kedua.
- Temuan data: og:image Bali `400×225` padahal nama file `w1280`; Solo `1024×576`. Metadata dimensions attachment perlu diperbaiki.
- Tidak ada `noindex` untuk eksperimen/published preview selain `preview_document()` lokal.

## 3. Status fitur vs permintaan V2

| Area | Kondisi | Gap |
|---|---|---|
| Page type registry | `offer_type` = product/service/affiliate, dipakai untuk CTA/disclosure/Amazon block | Belum strategy pattern; belum punya brief/schema/SEO/schema/link/CTA/quality/publish/analytics per tipe |
| Bahasa | `language` id/en, prompt dan UI sebagian localized | Belum slug per bahasa, hreflang, reciprocal translation, x-default, quality translation |
| Service LP | Campaign + framework + lokasi ada | Belum LocalValueGate/doorway gate, input evidence/portfolio/testimoni terstruktur |
| Product LP | Ada offer fields generik | Belum physical/digital/SaaS differentiation, status/availability, warranty/licensing, schema Product/SoftwareApplication |
| Amazon affiliate | Amazon class: region allowlist, Bing image scrape, deeplink dengan tag, disclosure | Scraping Bing Images berisiko terhadap kebijakan; belum ASIN, compliance checklist, authorized content, price/availability policy, Review/Article schema yang aman |
| Metadata engine | Yoast + template meta | Belum validator title/desc/heading/canonical/robots/OG/image/breadcrumb terpusat |
| Schema engine | Yoast graph saja | Belum registry milik plugin; harus ambil `@id` Yoast, jangan duplikat Organization/WebSite |
| Internal link engine | Variasi posisi + compact related + semantic memory | Belum content graph node/edge, admin approve/reorder, filter noindex/unpublished |
| Duplicate prevention | Shingle + semantic memory | Belum doorway gate, template ratio, locale duplicate, affiliate thin-page gate |
| Quality gate | Validasi struktural | Belum skor 6 dimensi + hard blockers + approval eksplisit |
| Publishing workflow | Status draft/generating/review/publishing/published/failed | Belum state lengkap, version history, rollback, audit trail, experiment promotion |
| Experiment mode | Preview noindex lokal | Belum mode eksperimen produksi, sitemap exclusion, staging indicator, promosi terkontrol |
| Testing | Custom PHP runner + Node admin harness + fixture Playwright manual | Belum PHPUnit/WordPress test suite, Lighthouse, schema validator, E2E otomatis |
| Admin | Kuat untuk generation lama | Belum page type switch, language switch, evidence input, SEO report dashboard |

## 4. Temuan keamanan

### Sudah baik

- AJAX: `check_ajax_referer('lpgen_action','nonce',false)` + `current_user_can()` di `Ajax::verify()`.
- Nonce juga dipakai di `CampaignUI::save_meta`.
- `$wpdb->prepare()` dipakai pada query dinamis utama.
- `sanitize_*`/`esc_*` luas di UI dan renderer.
- Image URL divalidasi: skema http(s), loopback/private IP ditolak (terdapat regression test).
- Secret (9Router key, Amazon tag) disimpan di WP option, tidak ditulis ke source; UI password untuk API key.

### Perlu diperbaiki

1. **Amazon/Bing image scraping** (`Amazon::scrape`) mengambil gambar dari hasil Bing Images. Ini berisiko terhadap ketentuan Amazon Associates dan hak cipta. Perlu diganti ke sumber resmi/berizin atau dinonaktifkan default.
2. **Tidak ada rate limiting** pada aksi AI generation selain lock job queue. Perlu job-level throttle + AI call budget.
3. **Tidak ada audit trail terstruktur** untuk siapa menyetujui/mempromosikan halaman eksperimen.
4. **Tidak ada validasi capability khusus** untuk promote experiment → production; rencana harus memakai capability eksplisit.
5. **Tidak ada SSRF guard terpusat** untuk semua URL eksternal; saat ini tersebar di ImagePipeline/Amazon/Updater. Perlu helper bersama.
6. **Tidak ada secret redaction test** untuk log/context.

## 5. Temuan performa & maintainability

- `Generator::generate_item()` adalah hotspot dengan ~32 callee — perlu dipecah menjadi pipeline steps.
- `ContentRepository::get_campaign()` dipanggil luas (fan-in 16) dan melakukan banyak meta read; perlu object cache per request.
- `Utils::truncate()` fan-in 27 — helper dasar, tetapi banyak pemakaian menandakan normalisasi tersebar.
- Tidak ada query object caching; batch generation berpotensi N+1 meta reads.
- CSS plugin terpisah dan scoped, sudah baik.
- Test suite custom masih kuat untuk regression, tapi belum standar WordPress/PHPUnit.

## 6. Keputusan arsitektur yang diusulkan

1. **Jangan rewrite total.** Pertahankan WP CPT, job queue, DesignContract, AiClient, Publisher, Yoast integration.
2. Tambahkan lapisan baru:
   - `LpTypeRegistry` + `LpTypeStrategy` (service/product/amazon_affiliate)
   - `LanguageService` (id/en, slug, hreflang plan, locale validation)
   - `SeoMetadataEngine` (validate + Yoast adapter, canonical/robots conflict detection)
   - `SchemaRegistry` (stable @id, ambil Organization/WebSite @id dari Yoast bila ada)
   - `ContentGraph` (nodes/edges + ranking)
   - `DuplicateGuard` + `LocalValueGate` + `AffiliateValueGate`
   - `QualityGate` (skor + hard blockers)
   - `ExperimentMode` + `PublishingWorkflow`
   - `LpAuditReport`
3. Semua modul baru memakai tabel/meta eksisting bila memungkinkan; tabel baru hanya jika benar-benar dibutuhkan (graph edges, workflow events).
4. Yoast tetap authority untuk canonical, robots, OG, sitemap, schema graph. Plugin hanya memberi input lewat filter/meta dan tidak membuat tag head duplikat.
5. Amazon mode default **compliance-first**: tanpa ASIN terverifikasi, tag afiliasi valid, marketplace config, dan authorized content source → tetap draft.

## 7. Migration plan (ringkas)

- `LPG_DB_VERSION` 2 → 3.
- Tambah tabel opsional:
  - `wp_lpg_graph_nodes`
  - `wp_lpg_graph_edges`
  - `wp_lpg_workflow_events`
- Semua CREATE TABLE via `dbDelta`, additive only. Tidak ada drop/rename pada deploy pertama.
- Backfill tidak langsung dijalankan; disediakan wizard admin “Audit & Backfill”.
- Rollback: modul V2 bisa dinonaktifkan via filter `lpgen_v2_enabled`; data lama tetap terbaca.
- Existing published URLs tidak diubah. URL `/id/...` `/en/...` hanya untuk page baru bila diaktifkan eksplisit.
- Perubahan meta baru memakai prefix `_lpgen_v2_` agar tidak menimpa meta lama.

## 8. Langkah implementasi berikutnya

Sesuai Step 3–8 permintaan:

1. Page type registry + language system.
2. Template/strategi service, product, affiliate.
3. SEO metadata + schema + internal link engines.
4. Duplicate prevention + quality gates.
5. Experiment mode + publishing workflow.
6. Tests unit/integration/E2E + report.

Setiap langkah akan diberi regression test dan dijalankan sebelum lanjut.
