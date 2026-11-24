# V2 Step 7 — Experiment mode & publishing workflow

Status: implemented locally, not yet committed/released.

Tidak ada publish otomatis. Promosi hanya membersihkan mode eksperimen; status publish tetap keputusan manual.

## 1. ExperimentMode

File: `includes/class-experiment-mode.php`

Mode:

```text
CONTENT_PREVIEW
SEO_VALIDATION
SCHEMA_VALIDATION
INTERNAL_LINK_TEST
MULTILINGUAL_TEST
PERFORMANCE_TEST
PUBLISH_READY
```

Default campaign baru: `CONTENT_PREVIEW` + `publish_status = draft`.

Campaign lama tanpa meta tetap diperlakukan sebagai produksi (tidak noindex).

Efek eksperimen:

- `noindex` aktif (`_lpgen_noindex` dan `_yoast_wpseo_meta-robots-noindex` saat Yoast ada)
- fallback `<meta name="robots" content="noindex,follow" />` hanya bila tidak ada plugin SEO
- `SeoStandards::refresh_related()` dan `Indexer::schedule()` dilewati
- tidak masuk sitemap (Yoast mengecualikan noindex)
- badge **Experiment · ...** hanya di admin, tidak di halaman publik

## 2. Promotion checks

`ExperimentMode::promotion_checks()` menghasilkan:

```text
quality, seo, schema, internal_links, multilingual,
performance, accessibility, security, approval
```

Nilai: `PASS`, `WARNING`, `FAIL`, `NOT TESTED`.

Keputusan akhir:

- `FAIL` bila ada FAIL
- `WARNING` bila ada WARNING / NOT TESTED
- `PASS` bila semuanya PASS

`ExperimentMode::promote()` menolak bila `FAIL`, menghapus `experiment_mode`, dan melepas noindex. Tombol promosi tersedia di Review UI dengan nonce + capability admin yang sama seperti aksi lain.

## 3. PublishingWorkflow

File: `includes/class-publishing-workflow.php`

State:

```text
DRAFT → RESEARCHED → GENERATED → VALIDATED → APPROVAL_PENDING
      → APPROVED → SCHEDULED → PUBLISHED → MONITORED → ARCHIVED
```

Jalur tambahan: `REVISION_REQUIRED`, `REJECTED`, `FAILED`.

Fitur:

- transisi divalidasi (tidak bisa loncat `DRAFT → PUBLISHED`)
- idempoten
- history maksimal 40 langkah di `_lpgen_v2_workflow_history`
- state di `_lpgen_v2_workflow_state`
- log via `Database::log`

Integrasi:

- `Generator` memanggil `mark_generated()` setelah item tersimpan
- `set-status approved` membuka jalur ke `APPROVED`
- `set-status rejected` masuk `REJECTED`
- `Publisher` memindahkan `APPROVED/SCHEDULED → PUBLISHED` saat publish berhasil

## 4. Admin UI

- Campaign edit: dropdown **Mode eksperimen**
- Campaign list: badge experiment
- Campaign detail: badge + tombol **Promosikan dari Eksperimen**
- Item table: badge workflow state + ringkasan `Quality: skor · decision`

## 5. Tests

```text
php tests\run.php → 998 passed, 0 failed
node tests\test-admin-runner.js → PASS
php tests\smoke-bootstrap.php → PASS
php -l → No syntax errors
node --check admin/js/admin.js → PASS
```

Regression:
- mode tidak dikenal → produksi
- default campaign baru = CONTENT_PREVIEW + draft + noindex
- fallback robots hanya untuk eksperimen
- promosi ditolak sesuai checks
- workflow transisi valid/idempoten/history
- publish dari DRAFT dilarang
- NOT TESTED → WARNING, bukan FAIL
