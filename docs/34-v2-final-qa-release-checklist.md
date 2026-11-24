# V2 Final QA & Release Checklist

Status: QA lokal selesai. **Belum commit/push/release.** Keputusan go/no-go: **NO-GO untuk produksi sampai validasi WordPress staging selesai.**

## 1. Acceptance criteria

| Kriteria | Status | Bukti |
|---|---|---|
| 3 tipe LP (service/product/amazon_affiliate) | PASS | `test-prompts.php`, registry test |
| ID/EN penuh untuk UI publik | PASS | `test-zz-v2-integration.php`; Playwright EN tidak ada string UI ID |
| Tidak ada label TOFU/MOFU/Tag/Split Proof di publik | PASS | `test-z-aida-contract.php`, `test-zz-v2-integration.php`, Playwright |
| Yoast tetap authority; focus keyword tidak ditimpa | PASS | `test-zzz-yoast-focus.php` |
| Schema tanpa duplikat @id / affiliate tanpa rating rekaan | PASS | `test-zz-v2-integration.php` |
| Duplicate prevention + value gates + quality score | PASS | `test-prompts.php` Step 6 |
| Experiment mode: draft/noindex/sitemap exclusion | PASS | `test-prompts.php` Step 7 |
| Workflow state machine dengan history | PASS | `test-prompts.php` Step 7 |
| Mobile 390px 2 kolom, tanpa overflow | PASS | Playwright `scrollWidth === clientWidth === 390`, grid `166px 166px` |
| Footer mengikuti city + target_areas | PASS | Playwright: `Area layanan: Solo, Sukoharjo, Karanganyar` / `Service area: Bali, Denpasar` |

## 2. Commands & results

```text
php -l (semua .php)                     → 0 error
node --check (semua .js)                → 0 error
php tests/run.php                       → 1073 passed, 0 failed
node tests/test-admin-runner.js         → PASS
php tests/smoke-bootstrap.php           → PASS (43 hook groups)
Playwright 390x844 + 1280x900           → PASS (4 preview: service-id, product-en, affiliate-id, affiliate-en)
```

Playwright metrics:

```text
service-id   mobile 390/390  grid 166px 166px  footer Area layanan: Solo, Sukoharjo, Karanganyar
product-en   mobile 390/390  grid 166px 166px  footer Service area: Bali, Denpasar
affiliate-id mobile 390/390  grid 166px 166px  footer Area layanan: Solo
affiliate-en mobile 390/390  footer Service area: Bali  disclosure EN  CTA EN  zero ID UI string
desktop      mobile 1280/1280 (semua preview)
```

Satu-satunya error konsol lokal: `favicon.ico 404` dari PHP dev server, bukan aplikasi.

## 3. Perubahan utama

- Step 3: `LpTypeRegistry`, `LanguageService`, `lp_type`
- Step 4: `LpBriefGenerator`, section schemas, 3 template visual
- Step 5: `SeoMetadataEngine`, `SchemaRegistry`, `InternalLinkEngine`
- Step 6: `DuplicateGuard`, `LocalValueGate`, `AffiliateValueGate`, `QualityGate`
- Step 7: `ExperimentMode`, `PublishingWorkflow`
- Step 8: perbaikan lokal UI EN, regression test integrasi, dokumentasi ini

## 4. Risiko residual

1. **Belum ada kredensial WordPress/9Router di environment ini.** Publish nyata, Yoast head output, IndexNow/PuSH, dan sitemap tidak diverifikasi langsung.
2. **Isi artikel EN pada fixture masih bahasa Indonesia** karena fixture statis. UI sudah penuh EN; konten AI EN harus diverifikasi saat generate nyata.
3. `promotion_checks` masih menghasilkan `NOT TESTED` untuk schema/multilingual/performance; ini sengaja ditandai `WARNING`, bukan `FAIL`.
4. Gate bersifat editorial/internal; tidak mengubah status publish otomatis.

## 5. Rollout (saat disetujui)

1. Backup database + folder plugin produksi.
2. Deploy paket plugin ke staging, aktifkan, jalankan smoke halaman admin.
3. Buat 1 campaign eksperimen baru: verifikasi default `CONTENT_PREVIEW`, `draft`, `noindex`.
4. Generate 1 LP per tipe + artikel pendukung; cek related card, footer lokasi, schema, canonical, focus keyword.
5. Uji mobile 390px dan desktop.
6. Promosi eksperimen hanya setelah checks ≥ WARNING dan approval manual.
7. Publish manual; verifikasi Yoast canonical/robots/OG/Twitter/JSON-LD tidak duplikat.
8. Monitor 24 jam: 404, ranking/impression, log plugin, IndexNow response.

## 6. Rollback

- Nonaktifkan plugin V2 dan kembalikan paket v1.9.3.
- Meta tambahan (`_lpgen_v2_*`, `_lpgen_experiment_mode`, `_lpgen_noindex`) bisa dibiarkan; tidak dipakai v1.9.3.
- Jika noindex masih muncul: hapus `_yoast_wpseo_meta-robots-noindex` dan `_lpgen_noindex` pada post terdampak, lalu flush cache.
- Jika workflow tersangkut: set `_lpgen_v2_workflow_state` ke state valid sesuai transisi, atau hapus meta untuk reset ke DRAFT.

## 7. Go / No-Go

**NO-GO untuk produksi** sampai item risiko #1 dan #2 diverifikasi di staging dengan kredensial nyata. Semua pemeriksaan lokal lolos.
