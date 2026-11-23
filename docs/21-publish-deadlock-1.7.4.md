# v1.7.4 — Publish deadlock LO/artikel selesai

## Kronologi dari log produksi

```text
17:39 Campaign status → review
17:39 Publish started with 6 items and 3 tag jobs
17:43 Job 109 gagal: Path LP bertingkat memerlukan post type Page
17:50–20:46 Job artikel 110–120 gagal berulang:
          BOFU belum terbit. Artikel ditahan agar tidak kehilangan link ke LP tujuan.
```

Item 1198–1208 **berhasil di-generate** (outline lolos, sebagian gambar jadi). Jadi masalahnya bukan AI, melainkan **publish LP**.

## Akar masalah

Campaign memiliki `bofu_path` bertingkat, misalnya:

```text
jasa/website/solo
```

tetapi `lp_post_type` bukan `page`. `Publisher::publish_item()` menolak dengan:

```text
Path LP bertingkat memerlukan post type Page.
```

Karena job publish LP gagal, `find_published_permalink('bofu')` selalu kosong. Setiap artikel yang dipublish membutuhkan permalink BOFU untuk internal link, sehingga semua artikel ikut gagal. Ini deadlock: **LP tidak publish → artikel tidak publish → tidak ada Success log**.

## Perbaikan

### 1. Post type dipaksa Page

`Publisher::publish_item()` kini tidak melempar error. Jika `bofu_path` tidak kosong dan post type bukan `page`, plugin mencatat warning dan memakai `page`.

### 2. Induk dibuat otomatis

Untuk path `jasa/website/solo`:

```text
/jasa          → dibuat jika belum ada
/jasa/website  → dibuat dengan post_parent = /jasa
/solo          → LP dengan post_parent = /jasa/website
```

Halaman induk dibuat sebagai `publish`. Bila sudah ada tetapi masih draft, statusnya diubah ke publish. Path yang sudah dipakai post lain tetap ditolak untuk menghindari tabrakan.

### 3. Normalisasi saat save campaign

`CampaignUI::save_meta_data()` kini menyimpan `lp_post_type = page` otomatis bila `bofu_path` mengandung `/`.

### 4. Preflight sebelum publish

`Generator::start_publish()` juga memperbaiki campaign lama yang belum tersimpan ulang: jika path bertingkat dan post type bukan page, meta campaign diperbarui ke `page` sebelum antrean dibuat.

### 5. Artikel menunggu, bukan langsung gagal

Sebelumnya:

```text
BOFU belum terbit → throw → retry 3× → failed permanen
```

Sekarang:

```text
BOFU belum terbit → requeue 10 menit → maksimal 3× → baru failed
```

Counter disimpan di `_lpgen_bofu_wait` per item dan dihapus saat artikel berhasil publish.

## Dampak yang diharapkan

- LP publish berhasil pada attempt berikutnya.
- Artikel yang tadinya gagal bisa dipublish ulang setelah LP tersedia.
- Internal link aktif karena `Publisher::insert_funnel_link()` menerima permalink BOFU.
- Log `Success` mulai terisi; `0 Success` bukan lagi kondisi normal.

## Catatan gambar

Warning berulang:

```text
Image fetch failed for keyword "Ilustrasi calon pelanggan bingung ..."
```

bersifat non-blocking. Artikel tetap terbit tanpa gambar. Ini akan ditangani terpisah lewat policy placeholder/regenerate (Increment D dari roadmap adaptasi).

## Verifikasi

```text
php tests\run.php → 883 passed, 0 failed
php -l → No syntax errors
```

Regression baru:

- path bertingkat tanpa induk → LP tetap terbit,
- halaman induk otomatis dibuat,
- slug terakhir dan `post_parent` sesuai path,
- republish tidak membuat duplikat.
