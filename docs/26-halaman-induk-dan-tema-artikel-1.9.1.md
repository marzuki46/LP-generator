# v1.9.1 — Halaman induk bertingkat & tema artikel

## 1. Masalah dari screenshot mobile

Screenshot menunjukkan halaman induk tampil dengan tema WordPress default: daftar link panjang, tidak ada styling, tidak responsif. Penyebabnya: halaman induk dibuat sebagai post biasa tanpa wrapper `lpg-lp` dan tanpa `_lpgen_canvas`.

Perbaikan:

- Halaman induk sekarang berupa **satu Custom HTML block** dengan wrapper:
  ```html
  <div class="lpg-lp lpg-lp--parent lpg-lpm lpg-lpm--<model>" ...>
  ```
- Meta `_lpgen_canvas = 1` ditambahkan agar template plugin dan CSS dipakai penuh.
- Layout diuji di 390×844: tidak ada overflow, menu wilayah dan Contact Us tampil sebagai card.

## 2. Input halaman induk

Di edit campaign, untuk setiap level path (misal `jasa` dan `jasa/website`) tersedia:

- **Judul**
- **Pengantar**
- **Isi halaman (2–3 paragraf)**
- **Teks tombol lanjutan**

Data disimpan di `parent_pages` sebagai JSON per path. Jika kosong, judul dibuat dari slug dan konten tetap menghasilkan menu + Contact Us.

## 3. Menu per wilayah

`LpParentPages::regions()` mencari campaign lain dengan `bofu_path` satu level di bawah path induk, lalu merender card:

```text
WILAYAH
Jasa Website Solo
Lihat halaman →
```

Campaign saat ini tetap dimasukkan bila path-nya cocok. Maksimal 12 card, deduplikasi by URL.

Dengan ini halaman `/jasa/` berisi daftar kategori, `/jasa/website/` berisi daftar LP per kota, dan LP `/jasa/website/solo/` fokus ke penawaran Solo. Tidak tercampur antar wilayah.

## 4. Contact Us per campaign

Field baru:

- Telepon / WhatsApp (`phone`, sudah ada)
- Email (`contact_email`)
- Alamat (`contact_address`)
- Jam operasional (`contact_hours`)

Ditampilkan di footer halaman induk **dan** LP. Nomor telepon otomatis dibuatkan tautan `wa.me`. Data ini milik campaign tersebut, bukan data global.

## 5. Tema artikel pendukung

Field baru: **Tema artikel pendukung** dengan pilihan:

| Tema | Karakter |
|---|---|
| Editorial | Garis aksen orange, heading dengan marker |
| Magazine | Lebar, heading uppercase, lead paragraph |
| Minimal | Sempit, heading bergaris bawah |

Artikel TOFU/MOFU/tag campaign dibungkus dalam `wp:group` dengan class `lpg-article lpg-article--<theme>`. CSS ada di `public/css/article-themes.css` dan dimuat otomatis pada post yang memuat `lpg-article`.

## 6. Label model dihapus

Teks seperti **Split Proof** / **Editorial SaaS** tidak lagi ditampilkan di nav LP. Model tetap tersimpan di `data-lp-model` dan class `lpg-lpm--*` untuk styling.

## 7. Verifikasi

```text
php tests\run.php → 917 passed, 0 failed
php -l → No syntax errors
Playwright mobile 390×844 → halaman induk rapi, tidak overflow
```

## 8. Catatan

- Halaman induk yang dibuat manual tidak akan ditimpa; hanya yang punya meta `_lpgen_parent_page`.
- Setelah mengubah input induk, lakukan publish ulang LP untuk memperbarui halaman induk.
- Blog per wilayah memakai campaign per wilayah; artikel tag tetap diarahkan ke LP induk campaign masing-masing.
