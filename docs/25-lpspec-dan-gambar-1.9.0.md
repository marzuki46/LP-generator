# v1.9.0 — Canonical lpSpec & lifecycle gambar

Implementasi Increment A + D dari roadmap `docs/20-sintesis-adaptasi-openpage-automattic.md`.

## 1. Increment A — Canonical spec + warnings

### `lpSpec`

- File: `lp-generator/schemas/lp-spec.schema.json` (JSON Schema Draft 2020-12).
- Field tetap: `campaign_id`, `offer_name`, `offer_url`, `offer_type`, `audience`, `problem`, `promise`, `benefits`, `price`, `city`, `language`, `framework`, `framework_slug`, `lp_model`, `lp_target_keyword`, `sections`, `sources`, `entities`, `created_at`, `checked_at`.
- `additionalProperties: true` untuk fakta host tambahan (jam buka, lokasi, layanan, dll.).
- `sections` mengikuti `DesignContract`: `id`, `step`, `hint`, `layout`, `image_slot`, `role`, `shape`, plus `heading_goal`, `image_keyword`, `table_needed`, `pain_points`, `cta_type`, `internal_link_target`.

`LpSpec::normalize()`:

- mengisi nilai kosong dari campaign,
- menjaga jumlah section sesuai kontrak,
- tidak mengubah `created_at`/`checked_at` yang sudah ada,
- idempoten: `normalize(normalize(x)) === normalize(x)`.

Spec disimpan di meta item `_lpgen_spec` setelah generate.

### `LpWarnings`

Disimpan di `_lpgen_warnings` sebagai:

```json
{ "outline": ["authored=...; delivered=...; disposition=..."] }
```

- `add()` menambah dengan deduplikasi.
- `replace()` mengganti satu step tanpa mengubah step lain.
- Pesan dipotong 160 karakter.

Warning dibuat bila `heading_goal` atau `image_keyword` kosong. Kosong bukan lagi alasan job gagal.

## 2. Increment D — Lifecycle gambar

Tiap gambar kini punya status:

| Status | Arti |
|---|---|
| `completed` | Gambar AI/Bing/upload berhasil |
| `placeholder` | Placeholder WebP brand dipakai karena engine gagal |
| `failed` | Gagal total |

Status disimpan di:

- `_lpgen_image_specs` per item,
- `visual_status` pada tiap record gambar,
- `_lpgen_visual_status` pada attachment.

Di Review, item dengan placeholder/failed mendapat tombol **Ganti gambar** (`regenerate-image`). Aksi ini:

1. menghapus transient kegagalan untuk keyword terkait,
2. membuang hanya record placeholder/failed,
3. memanggil ulang `ImagePipeline::prepare_for_item`,
4. mempertahankan gambar `completed` yang sudah ada.

Copy artikel/LP tidak disentuh.

## 3. Yang belum dari roadmap

- Increment B lanjutan: token architecture penuh 3-layer (primitive → semantic → component). Saat ini `VisualIdentity` + 4 model sudah ada, tapi belum semua warna memakai token CSS.
- Increment C: step graph dengan `reads`/`writes`, resume `--from/--until`, dan model tier per step.

## 4. Verifikasi

```text
php tests\run.php → 915 passed, 0 failed
node tests\test-admin-runner.js → PASS
php tests\smoke-bootstrap.php → PASS (41 hook groups)
php -l → No syntax errors
```

Regression:

- 4 section AIDA sesuai kontrak,
- normalisasi idempoten,
- schema file tersedia di paket,
- warning duplikat tidak bertambah,
- keyword gagal di-cache & diganti fallback (dari rilis sebelumnya).

## 5. Batasan

- Belum ada validator JSON Schema runtime; normalizer PHP yang menjamin bentuk.
- Tombol Ganti gambar mengubah data item, bukan otomatis post publish—publish ulang tetap diperlukan.
- WP produksi dan 9Router live belum diuji.
