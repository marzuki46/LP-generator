# V2 Step 6 — Duplicate prevention, value gates & quality gate

Status: implemented locally, not yet committed/released.

Tidak ada status publish yang diubah otomatis. Semua hasil gate disimpan sebagai laporan dan warning.

## 1. DuplicateGuard

File: `includes/class-duplicate-guard.php`

Mendeteksi:

- judul identik
- judul city-swapped (dengan `known_cities` dari context/campaign)
- kemiripan isi via `FunnelContent::similar()`
- pola heading berulang
- canonical bentrok
- memori semantik berisiko tinggi

Output:

```text
score 0–100, risk low|medium|high, reasons[]
```

## 2. LocalValueGate

File: `includes/class-local-value-gate.php`

Sebelum city LP naik ke produksi, harus ada:

- `service_covered`
- `local_need`
- `local_examples`
- `geographic_claims`
- `architecture_position`

Keputusan:

- `PASS` — bukti lengkap dan diferensiasi lokal ≥ 40
- `REVIEW_REQUIRED` — ada bukti kosong
- `HOLD` — diferensiasi < 40 atau klaim lokal terverifikasi tidak ada

Rekomendasi bila diferensiasi rendah: **`area_hub`** (jangan buat LP kota berulang).

Gate tidak mengarang fakta lokal.

## 3. AffiliateValueGate

File: `includes/class-affiliate-value-gate.php`

Wajib:

- `amazon_marketplace` sesuai allowlist `Amazon::REGIONS`
- ASIN
- affiliate tag
- source reference
- original analysis minimal 120 karakter

Ditahan bila:

- ASIN/tag/source kosong
- analisis terlalu pendek
- klaim “I tested / saya sudah mencoba” tanpa `testing_evidence`
- data mengandung `aggregateRating` atau `priceSpecification` rekaan

## 4. QualityGate

File: `includes/class-quality-gate.php`

Bobot internal:

| Dimensi | Bobot |
|---|---:|
| Search intent alignment | 20 |
| Unique information / evidence | 25 |
| Useful content & completeness | 20 |
| Factual reliability | 15 |
| Technical SEO | 10 |
| Readability & UX | 10 |

Keputusan:

- `ELIGIBLE_FOR_REVIEW` ≥ 85
- `REVISION_REQUIRED` 65–84
- `HOLD` < 65
- **Hard blocker selalu mengalahkan skor**

Hard blocker meliputi: SEO blocker, duplicate high risk, local/affiliate gate HOLD, dan CTA utama kosong pada LP.

Laporan disimpan di `_lpgen_v2_quality`; warning masuk `LpWarnings` step `quality`.

Integrasi: `Generator::record_quality_report()` dipanggil setelah `save_item_result()`.

## 5. Tests

```text
php tests\run.php → 975 passed, 0 failed
node tests\test-admin-runner.js → PASS
php tests\smoke-bootstrap.php → PASS
php -l → No syntax errors
```

Regression:
- city-swapped title terdeteksi
- local gate REVIEW_REQUIRED / HOLD / PASS + area_hub
- affiliate gate tanpa ASIN ditahan
- affiliate PASS dengan data lengkap
- klaim hands-on tanpa bukti ditahan
- hard blocker override skor
- bobot 25 untuk unique evidence
