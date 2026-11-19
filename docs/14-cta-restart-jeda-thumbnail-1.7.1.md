# v1.7.1 — URL CTA, restart, jeda, dan thumbnail

## 1. URL CTA tidak lagi hilang

Gejala: `LP membutuhkan URL CTA campaign yang valid dan teks tombol`, walaupun URL sudah diisi.

Akar masalah: `Utils::clean_url()` mengembalikan string kosong untuk input umum seperti:

```text
wa.me/6281234567890
domain.com/beli
6281234567890
```

Perbaikan:

- `https://` ditambahkan otomatis untuk domain tanpa skema.
- Nomor 8–15 digit dinormalisasi ke `https://wa.me/<nomor>`.
- URL lengkap dengan query (`?text=...`) dipertahankan.
- Di `CampaignUI::save_meta_data()`, input yang tetap invalid **tidak dibuang**: disimpan apa adanya (sudah di-sanitize) dan dicatat sebagai warning, supaya terlihat di form dan bisa diperbaiki.
- Dashboard menampilkan peringatan merah **“URL CTA belum valid — isi di edit campaign.”** pada baris campaign yang `offer_url`-nya belum valid.

## 2. Tombol mulai dari awal

Dashboard `wp-admin/admin.php?page=lp-generator` kini punya tiga aksi per campaign:

- **Generate** — mulai antrean baru (perilaku lama).
- **Mulai dari Awal** — `restart-campaign`: menghapus job `queued`/`running`/`failed`, menghapus item lama, lalu memanggil `Generator::start()` lagi.
- **Proses Antrean** — jalankan satu job berikutnya.

`Generator::restart()` hanya menghapus job yang belum selesai; job `done` dan post yang sudah publish tidak dihapus.

## 3. Jeda antar konten + hitung mundur

Settings → AI → **Jeda antar konten (menit)**, default **2 menit**, rentang 0–60.

`Generator::start()` menjadwalkan job generation dengan:

```text
run_at = now + (sequence × gap_minutes)
```

Job pertama tetap langsung berjalan.

Dashboard runner sekarang menampilkan:

- progress bar,
- status teks,
- hitung mundur `Jeda berikutnya: MM:SS` yang berjalan tiap detik,
- daftar per item: judul, kind, status, attempts, dan error terakhir.

`Database::queue_snapshot()` menyertakan `items[]` lewat `queue_details()`. Worker terus melakukan polling 1 detik saat menunggu jadwal, bukan berhenti seperti sebelumnya.

Set `Jeda antar konten = 0` untuk kembali ke perilaku lama (semua job langsung berurutan).

## 4. Gambar artikel hanya thumbnail

Sebelumnya gambar artikel pendukung disisipkan sebagai galeri di dalam body. Sekarang:

- `Publisher::build_blocks()` melewati galeri untuk `kind = article`.
- `Publisher::publish_item()` menetapkan attachment pertama sebagai **featured image** (`set_post_thumbnail`).
- LP dan preview tetap memakai gambar inline seperti sebelumnya.

Ini membuat daftar blog/arsip menampilkan thumbnail tanpa duplikasi gambar di dalam artikel.

## File yang diubah

- `includes/class-utils.php` — normalisasi URL
- `includes/class-generator.php` — `restart()`, `run_at` jeda
- `includes/class-database.php` — `queue_details()`
- `includes/class-publisher.php` — thumbnail + tanpa galeri artikel
- `admin/class-ajax.php` — aksi `restart-campaign`
- `admin/class-campaign-ui.php` — runner detail/countdown
- `admin/class-review-ui.php` — tombol restart, peringatan CTA, runner
- `admin/js/admin.js` — ticker, detail list, restart action
- `admin/class-settings.php` — `content_gap_minutes`
- `tests/*` — regression URL, galeri artikel, harness interval

## Verifikasi

```text
php tests\run.php → 869 passed, 0 failed
node tests\test-admin-runner.js → PASS
php tests\smoke-bootstrap.php → PASS
php -l → No syntax errors
```

## Batasan

- Perubahan jeda berlaku untuk generation jobs baru; antrean lama tetap memakai `run_at` yang sudah tersimpan.
- Tombol restart membersihkan job gagal/berjalan; pastikan tidak ada worker browser lain yang masih aktif sebelum menekan.
- Featured image memakai attachment pertama yang sudah terverifikasi; jika tidak ada, tidak ada thumbnail yang diset.
- Belum diuji pada WordPress produksi/9Router live.
