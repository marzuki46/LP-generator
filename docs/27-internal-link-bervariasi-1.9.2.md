# v1.9.2 — Internal link bervariasi & kotak related

## 1. Masalah

Sebelumnya `Publisher::insert_funnel_link()` mencari anchor di paragraf pertama yang cocok, atau menambahkan paragraf baru di akhir. Akibatnya hampir semua artikel menaruh link ke LP di bagian bawah dan terasa dipaksakan.

## 2. Penempatan bervariasi

`Publisher::link_slot( $item_id, $anchor )` memilih salah satu dari:

```text
top | middle | bottom
```

secara deterministik dari `crc32(item_id|anchor) % 3`. Hasilnya stabil untuk item yang sama, tapi berbeda antar artikel.

`insert_funnel_link()` kini:

- mengumpulkan semua paragraf `<p>`,
- memilih paragraf sesuai slot, lalu mencari anchor di sana dan sekitarnya,
- jika anchor tidak ada di seluruh body, menyisipkan satu kalimat jembatan pendek di posisi slot—bukan selalu di footer,
- tidak memasang link di paragraf yang sudah memiliki `<a>`.

Anchor tetap berasal dari target keyword campaign (`Generator::internal_anchor()`), sehingga semua artikel menuju LP induk dengan keyword yang konsisten.

## 3. Kotak related 2×3

`Publisher::compact_related_box()` menghasilkan:

```html
<div class="lpg-inline-related">
  <a class="lpg-inline-related__card">
    <span class="lpg-inline-related__kicker">MOFU</span>
    <span class="lpg-inline-related__title">Judul konten</span>
    <span class="lpg-inline-related__excerpt">Ringkasan satu baris…</span>
  </a>
  ...
</div>
```

- Dua konten pendukung dari campaign yang sama, berbeda stage.
- Tiga baris per kartu: stage, judul, ringkasan.
- Diletakkan **berlawanan** dengan link utama: jika link utama di bawah, kotak di atas; jika link utama di atas/tengah, kotak di bawah.
- CSS: 2 kolom di desktop, 1 kolom di mobile, di `public/css/article-themes.css`.

## 4. Verifikasi

```text
php tests\run.php → 920 passed, 0 failed
php -l → No syntax errors
```

Regression baru:

- 24 item menghasilkan ketiga variasi slot,
- slot `top` meletakkan link sebelum paragraf kedua,
- slot `bottom` meletakkan link setelah paragraf ketiga.

## 5. Catatan

Konten yang sudah terbit perlu **publish ulang** agar penempatan link dan kotak related diperbarui. Perubahan editor tetap dipertahankan karena plugin memodifikasi `post_content` yang sudah ada, bukan membangun ulang dari JSON AI.
