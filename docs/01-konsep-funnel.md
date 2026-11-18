# 01 — Konsep Funnel TOFU → MOFU → BOFU

## 1. Prinsip

Landing page (BOFU) adalah **tujuan akhir**, bukan halaman yang berdiri sendiri. Artikel TOFU dan MOFU berfungsi sebagai jalan masuk (entry point) yang:

1. Menjawab pertanyaan pembaca pada tahap kesadaran mereka.
2. Membangun kepercayaan lewat edukasi, bukan hard selling.
3. Menutup dengan **internal link kontekstual** yang mengarah ke level funnel berikutnya.

Plugin menghasilkan seluruh paket ini dari satu campaign, sehingga namespace topik, sudut pandang, dan anchor link konsisten.

---

## 2. Tahap dan tujuan

| Tahap | Pertanyaan pembaca | Tujuan konten | Contoh tipe artikel | Headings tipikal |
|---|---|---|---|---|
| **TOFU** (Top of Funnel) | "Apa itu ini? Kenapa saya perlu peduli?" | Edukasi, awareness, jangkauan luas | Panduan pemula, daftar kesalahan umum, glossary, tren | Apa itu X · Kenapa penting · 5 kesalahan umum · Cara mulai |
| **MOFU** (Middle of Funnel) | "Mana yang cocok untuk saya?" | Pertimbangan, perbandingan, bukti | Review mendalam, perbandingan, studi kasus, "cara memilih" | Kriteria memilih · Tabel perbandingan · Kelebihan/kekurangan · Rekomendasi |
| **BOFU** (Bottom of Funnel) | "Saya siap. Apa langkah berikutnya?" | Konversi | Landing page produk / jasa / afiliasi | Hero · Benefit · Bukti · Offer · FAQ · CTA |

---

## 3. Peta internal link

```
TOFU #1 ─┐
TOFU #2 ─┼──► MOFU #1 ─┐
TOFU #3 ─┘   MOFU #2 ─┼──► BOFU (LP) ──► CTA offer
                       ┘
```

Aturan implementasi:

- **MOFU → BOFU wajib.** Anchor berasal dari field AI `anchor_suggestion`, divalidasi admin saat review. Contoh anchor: `lihat paket lengkapnya`, `coba alat ini`, `konsultasi gratis`.
- **TOFU → MOFU wajib** jika campaign memiliki minimal satu artikel MOFU yang dipublish. Jika tidak ada, fallback ke BOFU.
- Link ditempatkan pada **paragraf CTA terakhir** dengan kalimat transisi, misalnya:
  > "Setelah memahami dasarnya, langkah berikutnya adalah membandingkan pilihan yang tersedia. **Lihat perbandingan lengkap di sini** untuk memilih yang paling sesuai."
- Link tidak boleh `target="_blank"` untuk funnel internal (menjaga alur baca).
- CTA eksternal (afiliasi) **boleh** dibuka tab baru dan wajib `rel="sponsored nofollow"`.

---

## 4. Cara plugin menjaga koherensi topik

Sebelum generate artikel, plugin mengirimkan ke prompt AI:

- Ringkasan campaign (jenis offer, audiens, masalah, janji, benefit, harga/bonus/garansi).
- Daftar **semua judul topik** yang sudah dibuat dalam campaign (untuk mencegah duplikat).
- Framework copywriting aktif.
- Stage saat ini (MOFU/TOFU) dan target link berikutnya.
- Bahasa dan tone of voice.

Dengan demikian:

- Topik TOFU tidak tumpang tindih dengan topik MOFU.
- Setiap artikel MOFU menyimpan `funnel_link_angle` yang dipakai saat menyusun paragraf CTA.
- LP menjadi "satu sumber kebenaran" untuk nama offer, benefit, dan CTA.

---

## 5. Default jumlah konten

| Item | Default | Bisa diubah |
|---|---|---|
| LP (BOFU) | 1 | Tidak (satu campaign = satu LP) |
| Topik MOFU | 2 | Ya, 1–10 |
| Artikel MOFU | 2 | Ya, mengikuti jumlah topik |
| Topik TOFU | 3 | Ya, 1–10 |
| Artikel TOFU | 3 | Ya, mengikuti jumlah topik |

Total AI request per campaign dengan default: **1 + 2 + 2 + 3 + 3 = 11 request** (belum termasuk retry).

---

## 6. Status item

Setiap konten (LP, topik, artikel) disimpan sebagai post bertipe `lpgen_item` dengan status internal:

```
queued → generated → approved → published
                 ↘ rejected
                 ↘ failed (retry habis)
                 ↘ regenerating
```

- `queued`: job belum dijalankan.
- `generated`: sudah dari AI, menunggu review.
- `approved`: disetujui, siap dipublish.
- `published`: sudah jadi post/page publik + punya permalink.
- `rejected`: dibuang admin, tidak dipublish.
- `failed`: AI gagal setelah retry; bisa di-regenerate manual.

---

## 7. Contoh paket funnel — produk "Lumina Skincare Serum"

| Stage | Judul hasil AI | Target link |
|---|---|---|
| TOFU-1 | "Apa Itu Niacinamide dan Kenapa Kulit Berminyak Membutuhkannya?" | MOFU-1 |
| TOFU-2 | "5 Kesalahan Umum Saat Memakai Serum Wajah" | MOFU-1 |
| TOFU-3 | "Urutan Skincare Pagi yang Benar untuk Pemula" | MOFU-2 |
| MOFU-1 | "Perbandingan Serum Niacinamide: Mana yang Cocok untuk Kulit Sensitif?" | BOFU |
| MOFU-2 | "Studi Kasus: 30 Hari Pakai Serum Niacinamide 10%" | BOFU |
| BOFU | "Lumina Serum — Glow Tanpa Iritasi, Garansi 30 Hari" | CTA: /checkout |

---

## 8. Contoh paket funnel — jasa "Jasa Desain Landing Page"

| Stage | Judul hasil AI | Target link |
|---|---|---|
| TOFU-1 | "Kenapa Landing Page Penting untuk Iklan Meta Ads?" | MOFU-1 |
| TOFU-2 | "7 Elemen yang Wajib Ada di Halaman Jasa" | MOFU-2 |
| TOFU-3 | "Biaya Buat Landing Page: Tolok Ukur 2026" | MOFU-2 |
| MOFU-1 | "Landing Page Custom vs Template: Mana Lebih Menguntungkan?" | BOFU |
| MOFU-2 | "Proses Desain Landing Page dari Brief sampai Launch" | BOFU |
| BOFU | "Jasa Landing Page Siap Pakai dalam 7 Hari — Konsultasi Gratis" | CTA: /kontak |

---

## 9. Contoh paket funnel — affiliate "Rekomendasi Hosting"

| Stage | Judul hasil AI | Target link |
|---|---|---|
| TOFU-1 | "Apa Itu Hosting VPS dan Kapan Harus Pindah?" | MOFU-1 |
| TOFU-2 | "Panduan Memilih Hosting untuk Website Baru" | MOFU-1 |
| TOFU-3 | "5 Penyebab Website Lambat di Bulan Pertama" | MOFU-2 |
| MOFU-1 | "Perbandingan Hosting Shared vs VPS vs Cloud (2026)" | BOFU |
| MOFU-2 | "Review Mendalam: hosting X Setelah 6 Bulan Dipakai" | BOFU |
| BOFU | "Rekomendasi Hosting X — Analisis Jujur, Kelebihan & Kekurangan" | CTA: link afiliasi + disclosure |
