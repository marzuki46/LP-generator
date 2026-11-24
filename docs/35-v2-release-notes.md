# LP Generator 2.0.0 — Release Notes

Tag: `v2.0.0` · Package: `lp-generator-2.0.0.zip`

## Highlights

- **3 tipe landing page**: SERVICE, PRODUCT, AMAZON_AFFILIATE.
- **Bahasa ID/EN penuh** untuk UI publik, footer lokasi, disclosure afiliasi, dan label related.
- **SEO aman**: Yoast tetap menguasai canonical, robots, sitemap, OG/Twitter, dan graph. Plugin tidak membuat canonical/schema duplikat.
- **Anti-duplikat**: city-swapped title, kemiripan isi, heading berulang, dan canonical bentrok terdeteksi.
- **Gate kualitas**: skor 6 dimensi + hard blocker; laporan di `_lpgen_v2_quality`.
- **Local value gate**: menahan LP kota tanpa bukti lokal; diferensiasi rendah disarankan jadi area hub.
- **Affiliate value gate**: ASIN, tag, sumber, dan analisis orisinal wajib; klaim rating/harga/hands-on rekaan ditolak.
- **Experiment mode**: default campaign baru `CONTENT_PREVIEW` + draft + noindex; promosi terkontrol.
- **Publishing workflow**: state machine dengan riwayat dan transisi valid.
- **Mobile responsive**: related grid 2 kolom di 390px, tombol CTA tidak terpotong.

## Upgrade notes

- Campaign lama tanpa `experiment_mode` tetap produksi; tidak otomatis noindex.
- Campaign baru default draft + `CONTENT_PREVIEW`.
- Field baru: `lp_type`, `experiment_mode`.
- Meta baru: `_lpgen_v2_quality`, `_lpgen_v2_seo_meta`, `_lpgen_v2_workflow_state`, `_lpgen_v2_workflow_history`, `_lpgen_noindex`.

## Verification draft

```text
php tests/run.php             → 1073 passed, 0 failed
node tests/test-admin-runner.js → PASS
php tests/smoke-bootstrap.php   → PASS
PHP/JS lint                     → 0 error
Playwright 390px + 1280px       → PASS
```

## Known limitations

- Validasi langsung di WordPress produksi/9Router/IndexNow belum dilakukan.
- `promotion_checks` masih menandai schema/multilingual/performance sebagai `NOT TESTED`.
- Skor quality adalah sinyal editorial internal, bukan skor ranking Google.
