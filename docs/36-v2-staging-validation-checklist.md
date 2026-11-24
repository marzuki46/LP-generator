# V2 Staging Validation Checklist

Lakukan ini di WordPress staging sebelum go/no-go produksi. Jangan kirim kredensial/API key ke chat.

## 0. Preflight

- [ ] Backup database produksi/staging.
- [ ] Backup folder `wp-content/plugins/lp-generator/` yang aktif (v1.9.3).
- [ ] Catat versi aktif dan URL staging.
- [ ] Siapkan paket V2. Dari root repo:

```powershell
Compress-Archive -Path 'lp-generator\*' -DestinationPath 'lp-generator-v2-staging.zip' -Force
```

## 1. Install & activation

- [ ] Upload/aktifkan paket V2 di staging.
- [ ] Tidak ada fatal error/notice di WP Admin.
- [ ] Menu **LP Generator** tetap muncul.
- [ ] Halaman Settings, Campaigns, Review, Activity Log terbuka.

## 2. Campaign baru (ID)

Buat campaign baru:

- Nama: `V2 Validation ID`
- Offer type: **Service**
- Bahasa: **ID**
- Kota: `Solo`
- Target areas: `Solo`, `Sukoharjo`, `Karanganyar`
- Offer URL: URL internal valid (contoh `https://staging.test/jasa/website/`)
- Status Publish: harus default **draft**
- Mode eksperimen: harus default **CONTENT_PREVIEW**

Verifikasi:

- [ ] Dropdown mode eksperimen menampilkan `CONTENT PREVIEW`.
- [ ] Campaign list menampilkan badge `Experiment · CONTENT PREVIEW`.
- [ ] Campaign lama yang tidak punya meta tetap normal/tanpa badge.

## 3. Generate & review

- [ ] Jalankan generation sampai selesai.
- [ ] Review UI menampilkan badge workflow `GENERATED`.
- [ ] Ringkasan `Quality: <skor> · <decision>` muncul.
- [ ] Preview LP: tidak ada `TOFU`, `MOFU`, `Tag`, `Split Proof`.
- [ ] Preview related card memakai label `Panduan` / `Baca panduan`.
- [ ] Footer: `Area layanan: Solo, Sukoharjo, Karanganyar`.
- [ ] CTA tidak terpotong pada layar 390px; related grid 2 kolom.

## 4. Uji noindex eksperimen

- [ ] Set status publish campaign ke `publish` sementara mode eksperimen masih aktif.
- [ ] Publish satu item.
- [ ] Lihat source halaman: ada `noindex,follow` (Yoast atau fallback).
- [ ] Yoast robots = `noindex` bila WPSEO_VERSION aktif.
- [ ] URL tidak muncul di sitemap Yoast.
- [ ] Tidak ada job IndexNow/PuSH untuk URL ini.
- [ ] Halaman induk/campaign lain tidak ikut noindex.

## 5. Approve & promote

- [ ] Approve item: workflow naik ke `APPROVED`.
- [ ] Klik **Promosikan dari Eksperimen**.
- [ ] Mode eksperimen dihapus; badge hilang.
- [ ] Meta noindex dilepas.
- [ ] Jika checks `FAIL`, promosi harus ditolak dengan pesan jelas.

## 6. Publish produksi-staging

- [ ] Publish manual setelah promosi.
- [ ] View-source: **tepat satu** canonical, self-host, trailing slash konsisten.
- [ ] Robots = `index,follow,...`.
- [ ] OG/Twitter hanya satu set.
- [ ] JSON-LD: satu graph, `@id` unik, tidak duplikat Organization/WebSite dari Yoast.
- [ ] Focus keyword Yoast tetap berasal dari entity/konten, bukan SEO title.
- [ ] IndexNow/PuSH job masuk antrean hanya untuk halaman produksi.

## 7. Campaign baru (EN)

Buat campaign EN dengan struktur sama.

Verifikasi UI:

- [ ] `STARTING FROM`, bukan `MULAI DARI`.
- [ ] `Related guides`, `Read guide`, `Frequently asked questions`.
- [ ] `Service area: ...` di footer.
- [ ] Tidak ada string UI Indonesia pada chrome halaman.
- [ ] Konten artikel yang dihasilkan AI benar-benar bahasa Inggris (ini yang belum bisa diverifikasi lokal).

## 8. Amazon affiliate

Buat campaign affiliate dengan:

- ASIN nyata
- marketplace valid
- affiliate tag
- source reference
- original analysis ≥ 120 karakter

Verifikasi:

- [ ] Disclosure muncul sebelum CTA pertama.
- [ ] CTA: `Check availability on Amazon` / `Cek ketersediaan di Amazon`.
- [ ] Tidak ada `aggregateRating`, `priceSpecification`, klaim harga/stok, atau klaim “I tested” tanpa bukti.
- [ ] Gate menahan campaign tanpa ASIN/tag/source/analisis.

## 9. Mobile & aksesibilitas

- [ ] 390×844: tidak ada horizontal scroll.
- [ ] Related grid 2 kolom.
- [ ] Tombol CTA teks horizontal, tidak vertikal.
- [ ] Fokus keyboard terlihat pada kartu/tombol.
- [ ] Kontras teks pada offerbar masih terbaca.

## 10. Log & rollback

- [ ] Activity Log tidak ada error baru.
- [ ] Jika gagal: nonaktifkan V2, kembalikan paket v1.9.3.
- [ ] Bila noindex masih tertinggal: hapus `_yoast_wpseo_meta-robots-noindex` dan `_lpgen_noindex`, lalu flush cache.
- [ ] Bila workflow tersangkut: set ulang `_lpgen_v2_workflow_state` atau hapus meta tersebut.

## Hasil yang dibutuhkan untuk go/no-go

Untuk setiap nomor, catat **PASS / FAIL / TIDAK TERUJI** + bukti (screenshot, URL, atau potongan source). Khusus nomor 2, 4, 6, 7, dan 8 wajib PASS sebelum produksi.
