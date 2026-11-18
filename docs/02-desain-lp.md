# 02 — Desain Landing Page: Produk, Jasa, Affiliate

Dokumen ini menjelaskan **struktur konten** yang dihasilkan AI untuk tiga tipe offer. Struktur dirender sebagai Gutenberg core blocks, bukan HTML bebas, agar tetap editable di editor WP.

---

## 1. Prinsip desain bersama

Berlaku untuk ketiga tipe:

1. **Satu tujuan per halaman** — satu CTA utama yang diulang di hero dan di bawah.
2. **Headline berbasis outcome**, bukan deskripsi produk. "Glow tanpa iritasi" lebih baik daripada "Serum Niacinamide 10%".
3. **Bukti sebelum harga** — testimoni/studi kasus ditaruh sebelum blok harga.
4. **FAQ sebagai penolak keraguan** — minimal 4 pertanyaan, ditutup dengan CTA.
5. **Framework bukan hiasan** — setiap langkah framework harus menjadi section nyata, bukan sekadar label.
6. **Konten ramah review** — AI menghasilkan JSON terstruktur; admin bisa mengubah tiap section sebelum publish.

---

## 2. Tipe A — Produk

**Cocok untuk:** barang fisik / digital yang dijual langsung, harga jelas, ada garansi.

### Struktur section

| # | Section | Isi | Pemetaan framework |
|---|---|---|---|
| 1 | Hero | Headline outcome, subheadline, CTA utama, microcopy (mis. "Gratis ongkir") | Attention |
| 2 | Social proof bar | Rating, jumlah pelanggan, logo, press mention | Attention/Interest |
| 3 | Problem | Gambarkan situasi sebelum pakai produk | Interest / Problem |
| 4 | Solution + FAB | Feature → Advantage → Benefit, 3–5 item | Interest / Desire |
| 5 | Benefit grid | 6 benefit dengan ikon (blok paragraph + heading) | Desire |
| 6 | Spesifikasi | Tabel ringkas: ukuran, komposisi, isi paket | Desire |
| 7 | Testimoni | 3 quote + nama + konteks penggunaan | Prove / Conviction |
| 8 | Price stack | Harga normal → harga promo → nilai total | Push / Action |
| 9 | Bonus | 2–3 bonus dengan nilai yang disebut | Push |
| 10 | Guarantee | Garansi + durasi + prosedur klaim | Conviction |
| 11 | FAQ | 4–6 Q&A (pengiriman, bahan, cara pakai, retur) | Conviction |
| 12 | Final CTA | Headline ulang + tombol + microcopy risiko rendah | Action |

### Contoh prompt ringkas (dipakai `Prompts::build_lp()`)

```
Role: Copywriter konversi produk.
Framework: AIDA → FAB.
Offer: Lumina Serum, audiens: wanita 25-40 kulit berminyak.
Problem: kusam + iritasi dari produk keras. Promise: glow tanpa iritasi.
Benefit: [hydrating, non-comedogenic, brightening, aman kulit sensitif].
Price: Rp149.000 (normal Rp229.000). Bonus: pouch + panduan rutin.
Guarantee: 30 hari uang kembali.
Output JSON: title, seo_title, meta_description, sections[{heading, body, bullets}],
cta_text, cta_url, faq[{q,a}]. Jangan markdown. Jangan klaim medis.
```

---

## 3. Tipe B — Jasa

**Cocok untuk:** layanan profesional, konsultansi, agency, freelance. Yang dijual adalah **proses + hasil + kepercayaan**.

### Struktur section

| # | Section | Isi | Pemetaan framework |
|---|---|---|---|
| 1 | Hero outcome | "Hasil apa yang saya dapat dalam berapa lama" + CTA jadwalkan konsultasi | Awareness / Attention |
| 2 | Kredibilitas | Pengalaman, jumlah klien, sertifikasi, tools | Awareness |
| 3 | Pain | Masalah klien sebelum pakai jasa + biaya diam-diamnya | Problem / Agitate |
| 4 | After state | Kondisi ideal setelah proyek selesai | Solution / After |
| 5 | Bridge / Proses | 3–5 langkah kerja dengan deliverable tiap langkah | Bridge / Educate |
| 6 | Studi kasus | Before → intervensi → angka hasil | Prove |
| 7 | Paket layanan | 2–3 tier: Nama, cocok untuk, deliverable, harga/start from | Stimulate |
| 8 | FAQ | Timeline, revisions, pembayaran, kepemilikan file | Conviction |
| 9 | Final CTA | Konsultasi gratis + whatsapp/calendar link | Action |

### Catatan khusus

- Hindari "murah"; tonjolkan **ROI / waktu yang dihemat**.
- CTA default: "Jadwalkan konsultasi gratis".
- Jika ada portofolio, AI diminta menyisipkan placeholder `{PORTFOLIO_URL}` agar admin mudah mengganti.

---

## 4. Tipe C — Affiliate

**Cocok untuk:** review produk pihak ketiga dengan link komisi. **Kepercayaan lebih penting daripada hype.**

### Struktur section

| # | Section | Isi | Pemetaan framework |
|---|---|---|---|
| 1 | Hero verdict | "Produk X cocok untuk [siapa], tidak cocok untuk [siapa]" + disclose tipis | Attention |
| 2 | Disclosure blok | "Halaman ini mengandung link afiliasi; kami dapat komisi tanpa biaya tambahan untuk Anda." | Trust |
| 3 | Ringkasan cepat | Rating, harga, best for, versi gratis/berbayar | Comprehension |
| 4 | Problem & why we tested | Alasan review dilakukan | Problem |
| 5 | Comparison table | 3 alternatif + kolom criteria | Persuasion |
| 6 | Pro / kontra | 4+4 poin jujur | Persuasion |
| 7 | Fitur yang diuji | Fitur utama + hasil pengujian | Proof |
| 8 | Siapa cocok / tidak | Dua daftar eksplisit | Acceptance |
| 9 | Bonus dari kami | Panduan/template bila klik lewat link ini | Push |
| 10 | FAQ | Legalitas, garansi vendor, cara klaim bonus | Conviction |
| 11 | Final CTA | Tombol afiliasi + `rel="sponsored nofollow"` | Action |

### Catatan khusus

- AI **dilarang** mengklaim hasil yang tidak bisa dibuktikan.
- Selalu ada kalimat "harga dapat berubah, cek halaman resmi".
- Link afiliasi disimpan di meta `_lpgen_offer_url`; publisher menambahkan atribut `rel` otomatis.
- Disclosure harus tampil **sebelum** link afiliasi pertama.

---

## 5. Pemetaan framework → section

| Framework | Langkah | Cocok untuk |
|---|---|---|
| **AIDA** | Attention → Interest → Desire → Action | Produk, LP umum |
| **PAS** | Problem → Agitate → Solution | Jasa, affiliate review |
| **BAB** | Before → After → Bridge | Jasa, transformasi |
| **FAB** | Features → Advantages → Benefits | Produk, spesifikasi teknis |
| **QUEST** | Qualify → Understand → Educate → Stimulate → Transition → Act | Jasa konsultasi tinggi |
| **DAGMAR** | Awareness → Comprehension → Conviction → Action | LP dengan banyak bukti |
| **DIPADA** | Desire → Interest → Persuasion → Acceptance → Desire → Action | Penjualan relasional |
| **IDEPA** | Identification → Demonstration → Evaluation → Prescription → Acceptance | Produk teknis |
| **ACCA** | Awareness → Comprehension → Conviction → Action | Mirip DAGMAR, ringkas |
| **AISAS** | Attention → Interest → Search → Action → Share | Produk with social proof |
| **4P** | Picture → Promise → Prove → Push | LP singkat |
| **SPIN** | Situation → Problem → Implication → Need-payoff | Jasa B2B |

Preset custom bisa ditambahkan di **Settings → Frameworks** dengan format JSON:

```json
{
  "my_framework": {
    "label": "Framework Saya",
    "steps": ["Hook", "Masalah", "Bukti", "Tawaran", "Aksi"],
    "best_for": ["product"],
    "section_hint": ["hero", "pain", "proof", "offer", "cta"]
  }
}
```

---

## 6. Representasi blok Gutenberg

Contoh hasil publisher untuk headline + CTA:

```html
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Glow Tanpa Iritasi dalam 14 Hari</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Serum ringan untuk kulit berminyak...</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><div class="wp-block-button">
<a class="wp-block-button__link" href="https://contoh.com/checkout">Coba Sekarang</a>
</div></div>
<!-- /wp:buttons -->
```

Untuk affiliate, anchor CTA ditambahkan `rel="sponsored nofollow"`.

---

## 7. Field review yang ditampilkan ke admin

Sebelum publish, halaman Review menampilkan:

- Judul & SEO title
- Meta description + panjang karakter
- Daftar section (bisa dihapus di editor WP setelah publish)
- CTA text + URL (validate URL)
- FAQ
- Link funnel target
- Model AI yang dipakai + jumlah token
- Status: approve / reject / regenerate

Admin **tidak perlu** mengeditJSON secara manual; hasil sudah rapi dalam blok saat dipublish.
