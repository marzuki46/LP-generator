# LP Generator 1.5.0 — HTML, Framework dan CSS

## Hasil yang sudah tersedia

LP dibuat sebagai HTML semantik di dalam satu blok Custom HTML WordPress. Plugin menyertakan CSS lengkap untuk hero, tombol, section cerita, kartu manfaat/paket, urutan proses, tabel, bukti beratribusi, galeri, FAQ dan penawaran akhir. Artikel pendukung tetap menggunakan renderer artikel yang sudah ada.

Preset: AIDA, PAS, BAB, FAB, QUEST, DAGMAR, DIPADA, IDEPA, ACCA, AISAS, 4P dan SPIN. Mode Framework AI Khusus dapat membuat urutan sendiri.

Framework adalah urutan persuasi, bukan sekadar pergantian warna. Setiap campaign memiliki rencana AI tersendiri berisi tujuan section, layout, hint dan peran gambar. Untuk preset, nama dan urutan langkah wajib dipertahankan. Dua langkah Desire pada DIPADA tidak digabung. Mode AI khusus merancang 4–6 langkah. Hasil yang kehilangan, mengubah atau membalik urutan langkah ditolak, bukan diam-diam diganti AIDA.

## Cara membuat LP

1. Buat atau buka campaign. Pilih Produk, Jasa atau Afiliasi; isi offer, audiens, kota, manfaat dan URL CTA bisnis Anda.
2. Pilih Buat LP Baru. Bila memakai path bertingkat, halaman induknya harus sudah tersedia.
3. Pilih formula copywriting yang diinginkan. Pilih Framework AI Khusus bila ingin AI menentukan urutan baru.
4. Pilih Tampilan LP: Halaman LP penuh atau Ikuti layout tema WordPress.
5. Klik Mulai Generate. Jika belum ada rencana framework yang sesuai, plugin menyiapkannya otomatis sebelum menulis LP. Tombol Siapkan framework campaign dapat dipakai untuk membuat ulang rencana secara terpisah.
6. Buka Review, perluas Isi, SEO, anchor & entitas. Periksa pratinjau dan HTML WordPress. Edit isi melalui editor item; blok Custom HTML menyimpan markup LP.
7. Approve, lalu Publish & Index. Perubahan editor tetap digunakan ketika publish.

Satu campaign masih untuk satu LP. Impor banyak keyword belum termasuk rilis ini dan tetap menjadi pekerjaan berikutnya.

## CSS dasar dan CSS tambahan

CSS dasar aktif otomatis pada halaman yang berisi LP Generator. Lokasinya di plugin adalah `public/css/landing-page.css`. File yang sama disediakan terpisah sebagai `lp-generator-style.css`.

Buka LP Generator → Settings → Frameworks → CSS tambahan untuk semua LP. Tidak perlu menempel ulang seluruh CSS dasar. Tambahkan perubahan seperlunya, misalnya:

```css
.lpg-lp {
  --lpg-accent: #176b62;
  --lpg-ink: #173449;
  --lpg-muted: #4a6372;
  --lpg-tint: #edf5f7;
  --lpg-line: #cfdee3;
  --lpg-paper: #ffffff;
}

.lpg-lp .lpg-lp__button {
  border-radius: 24px;
}
```

Gunakan awalan `.lpg-lp` agar aturan yang Anda tulis menargetkan LP. Hindari selector umum seperti `body`, `h1` atau `a` pada CSS tambahan. Kosongkan kolom tersebut untuk kembali ke CSS bawaan. CSS tambahan dimuat setelah stylesheet dasar. HTML/script tidak diterima di kolom CSS; ini bukan pemeriksaan lengkap validitas sintaks CSS.

Untuk satu formula saja, gunakan misalnya `.lpg-lp--pas`. Untuk satu tipe offer, gunakan `.lpg-lp--service`, `.lpg-lp--product` atau `.lpg-lp--affiliate`. Warna, font, lebar, jarak, kartu dan komponen lain dapat disesuaikan melalui CSS. Font bawaan memakai font sistem tanpa ketergantungan Google Fonts atau page builder.

## Hubungan dengan tema WordPress

- Halaman LP penuh: memakai template plugin dengan `wp_head`, `wp_body_open`, filter konten dan `wp_footer`. Tidak menampilkan header/navigasi/judul artikel dari template tema. Stylesheet yang berasal dari direktori tema induk/anak serta global styles tema dilepas untuk halaman LP itu. Halaman biasa tidak diubah.
- Ikuti layout tema: mempertahankan template dan stylesheet tema. LP tampil di area konten tema, sehingga lebar dan aturan tema masih dapat memengaruhi hasil.
- CSS dasar memakai class khusus LP Generator dan tidak memerlukan kelas dari tema tertentu. Halaman dengan proteksi kata sandi mempertahankan template aslinya.
- Ini dirancang agar dapat dipakai lintas tema klasik dan blok. Belum diuji pada setiap tema dan page builder. Tema/plugin yang menyisipkan CSS inline agresif atau mengubah template melalui hook lain tetap perlu diuji di situs target. Tidak ada klaim bahwa seluruh tema di dunia sudah diverifikasi.

## Rencana AI per campaign

Rencana disimpan bersama ID campaign dan sidik data offer. Rencana campaign lain tidak dipakai. Perubahan offer, kota, audiens, manfaat, harga atau formula membatalkan rencana lama. Generate awal/regenerate LP akan menyiapkan rencana baru bila perlu. LP yang dibuat dengan data lama ditahan saat publish jika sidik datanya sudah berubah.

AI menulis data terstruktur, sedangkan plugin menghasilkan HTML dan CSS. Konten AI tidak boleh menyisipkan script, style atau iframe. CTA menggunakan URL dari campaign. Disclosure afiliasi ditempatkan sebelum CTA pertama. FAQ memakai elemen details/summary, sehingga tidak bergantung pada JavaScript.

## Pratinjau dan batas pengujian

`PRATINJAU-LP-FRAMEWORK.html` dapat dibuka secara lokal. Pilih 12 preset atau AI pada dropdown. Kontennya merupakan contoh deterministik jasa website Solo untuk memperlihatkan renderer, bukan hasil generasi 9Router langsung.

Verifikasi lokal:

- 361 assertion lulus pada logika plugin, renderer, isolasi tema, CSS tambahan dan alur perencanaan AI dengan respons HTTP simulasi.
- 13 mode framework lolos parser blok dan penyaring HTML resmi WordPress 6.4. Markup gambar/afiliasi ikut diperiksa.
- 26 skenario desktop 1440px dan ponsel 390px tanpa overflow halaman, dengan pemeriksaan interaksi FAQ.
- Bootstrap plugin: 37 grup hook dan dua callback aktivasi/deaktivasi.
- PHP dan JavaScript diperiksa sintaksnya.

Belum diuji pada instalasi WordPress lengkap, tema produksi pengguna, layanan 9Router langsung atau penerimaan indexing. phpcs/WPCS tidak tersedia di lingkungan ini.

HTML disimpan pada post/page, tetapi tampilannya memerlukan stylesheet plugin. Jika menyalin HTML ke situs lain tanpa plugin, tambahkan CSS dasar pada fasilitas CSS tambahan situs tersebut. CSS tidak ditanam sebagai tag style di konten karena penyaring WordPress dapat membuangnya.

Versi 1.5.0 tidak merombak LP yang sudah terbit secara otomatis. Generate/regenerate LP untuk mendapatkan renderer baru. Paket dan manifest baru disiapkan lokal; rilis GitHub belum dipublikasikan.
