# v1.8.0 — Model visual LP, target keyword, artikel tag

## 1. Masalah

LP yang dihasilkan masih terasa seperti artikel biasa: judul, paragraf, kartu, FAQ. Padahal yang diinginkan adalah **beberapa model LP** yang tinggal menerima JSON konten, lalu plugin yang mengubahnya menjadi layout landing page.

Selain itu:

- konten support (TOFU/MOFU/tag) perlu internal link langsung ke LP induk,
- anchor sebaiknya memakai target keyword campaign, bukan kata acak dari AI,
- artikel tag sering belum tampil karena menunggu BOFU publish.

## 2. Empat model visual LP

Pilihan baru di edit campaign: **Model desain LP**.

| Model | Karakter |
|---|---|
| **Editorial SaaS** | Hero navy, CTA orange, section bento putih/abu, cocok untuk jasa/produk premium. |
| **Split Proof** | Hero dua kolom dengan rail penawaran gelap, section dua kolom dengan bukti di kanan. |
| **Pricing Focus** | Hero terpusat, label “Paket & Investasi”, tabel/kartu harga diberi aksen orange. |
| **Minimal Direct** | Layout sempit, banyak ruang kosong, CTA sticky berwarna orange. |

Semua model:

- menerima JSON yang sama dari AI (title, intro, sections, faq, CTA, gambar),
- mempertahankan `section_id`, `image_slot`, `data-step`, `data-hint` untuk validator,
- menambahkan `data-lp-model` dan class `lpg-lpm--<model>`,
- memakai token warna yang sama dengan `VisualIdentity` (`#1A1A2E`, `#FF6B35`, `#FFD166`),
- punya nav, trust strip, sticky CTA, slot related links, dan footer.

CSS ada di `public/css/lp-models.css`, dimuat otomatis di frontend, editor, dan preview.

## 3. Target keyword & internal link

Field baru: **Target keyword LP (anchor internal link)** di edit campaign.

- Jika diisi, anchor ini dipakai semua artikel TOFU/MOFU/tag.
- Jika kosong, plugin memakai `Nama penawaran + Kota`.
- Anchor dibatasi 10 kata / 100 karakter oleh `FunnelContent::anchor()`.
- Semua artikel tetap menuju permalink LP BOFU yang sudah publish.

## 4. Artikel tag langsung dilepas

Sebelumnya artikel yang menunggu BOFU diberi jeda 10 menit. Kini begitu LP berhasil publish, `Generator::publish_job()` langsung mencari item `approved` yang memiliki `_lpgen_bofu_wait`, lalu membuat job publish baru untuk mereka (termasuk artikel tag) dalam 30 detik.

Ini menghilangkan kondisi “tag article generated and scheduled” tetapi tidak pernah tampil.

## 5. File baru/ubah

Baru:

- `includes/class-lp-template.php`
- `public/css/lp-models.css`
- `docs/23-model-visual-lp-1.8.0.md`

Diubah:

- `includes/class-landing-page.php` — shell model + CSS
- `includes/class-aida-layout.php` — model class + shell
- `includes/class-content-repository.php` — `lp_model`, `lp_target_keyword`
- `admin/class-campaign-ui.php` — pilihan model & target keyword
- `includes/class-generator.php` — anchor target keyword + pelepasan artikel
- `includes/class-publisher.php` — memakai anchor target keyword

## 6. Verifikasi

```text
php tests\run.php → 907 passed, 0 failed
php -l → No syntax errors
Playwright: 4 model LP dirender dari JSON fixture, full-page screenshot, desktop 1440px
```

Screenshot lokal mengonfirmasi perbedaannya: hero navy (Editorial), rail gelap (Split), fokus harga (Pricing), dan CTA sticky orange (Minimal). Tidak ada teks yang tertutup warna gelap setelah perbaikan kontras.

## 7. Batasan

- Model mengubah **visual shell**, bukan urutan copywriting; AIDA/PAS/12 framework tetap menentukan struktur pesan.
- Tidak ada editor drag-and-drop JSON; model dipilih dari dropdown campaign.
- WP produksi dan 9Router live belum diuji.
