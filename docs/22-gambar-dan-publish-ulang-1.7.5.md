# v1.7.5 — Gambar selalu ada & publish ulang tanpa regenerate

## 1. Kenapa gambar berulang kali gagal

Dari log:

```text
Image fetch failed for keyword "Ilustrasi calon pelanggan bingung mencari info kontak resmi…"
Image fetch failed for keyword "Mockup tampilan website bisnis modern pada layar ponsel dan…"
```

Akar masalah:

- `image_keyword` dari rencana AI bisa berupa kalimat panjang dan abstrak.
- `build_ai_prompt()` mengulang keyword panjang itu di `Visual concept`, sehingga prompt gambar jembut dan model/image engine menolak atau menghasilkan hasil buruk.
- `build_bing_query()` menerima kalimat panjang yang sama, sehingga pencarian tidak mengembalikan hasil.
- Tidak ada cache kegagalan: setiap artikel mencoba keyword yang sama lagi.

## 2. Perbaikan

### Keyword dipangkas

`ImagePipeline::short_keyword()` memotong di batas kata, maksimal 60–80 karakter, tanpa `...`. Dipakai oleh:

- `build_ai_prompt()` → `Topic`, `Visual concept`, `Main subject`
- `build_bing_query()` → query pencarian

Contoh:

```text
sebelum: Ilustrasi calon pelanggan bingung mencari info kontak resmi pada website bisnis lokal...
sesudah: Ilustrasi calon pelanggan bingung mencari info kontak
```

### Cache kegagalan

Keyword yang gagal di semua engine disimpan sebagai transient selama 6 jam. Artikel berikutnya otomatis memakai keyword fallback dari entities/title/offer, bukan mengulang yang sama.

### Placeholder brand

Bila upload, AI, dan Bing semuanya gagal, plugin membuat WebP lokal 1280×720 dengan palet tetap:

```text
background #1A1A2E
akselerasi #FF6B35
highlight #FFD166
```

Komposisi: blok fokus di kanan, garis pendukung di kiri, **tanpa teks**. Ini mengikuti aturan `VisualIdentity` dan tetap memenuhi rasio 16:9.

Placeholder diberi meta:

```text
_lpgen_visual_status = placeholder
```

sehingga nanti bisa dibedakan dari gambar AI/Bing untuk regenerasi selektif.

### Ukuran AI default

Default `image_ai_size` diubah dari `1024x1024` menjadi **`1536x1024`** agar sesuai 16:9. Crop deterministik tetap berlaku untuk gambar AI/Bing.

## 3. Tombol publish ulang

Campaign dengan status `failed` sekarang menampilkan tombol **Coba Publish Ulang**. Ini memanggil `start_publish()` lagi tanpa menghapus item yang sudah tergenerate.

Sebelumnya tombol Publish hanya muncul untuk status `review`/`publishing`, sehingga campaign yang gagal di publish harus di-regenerate seluruhnya.

## 4. Dampak

- Tidak ada lagi artikel tanpa thumbnail karena engine gambar gagal.
- Request 9Router gambar berkurang karena keyword gagal tidak diulang.
- Campaign yang gagal publish bisa dipulihkan tanpa kehilangan konten.
- Log `Success` tetap muncul setelah LP/artikel berhasil publish; placeholder tidak dihitung sebagai error.

## 5. Verifikasi

```text
php tests\run.php → 888 passed, 0 failed
php -l → No syntax errors
```

Regression baru:

- keyword panjang dipangkas tanpa elipsis,
- query Bing memakai keyword pendek,
- keyword gagal di-cache dan diganti fallback,
- placeholder WebP lokal dibuat bila GD tersedia.

## 6. Batasan

- Placeholder adalah ilustrasi abstrak, bukan pengganti relevansi visual. User tetap disarankan mengganti dengan upload/AI saat tersedia.
- Cache kegagalan 6 jam bisa membuat keyword yang sebelumnya gagal tidak dicoba lagi; gunakan regenerate item bila ingin mencoba ulang lebih cepat.
- Integrasi reference image/style reference belum tersedia.
