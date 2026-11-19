# LP Generator — Workspace

Plugin WordPress yang mengubah satu campaign offer menjadi funnel konten lengkap (TOFU → MOFU → BOFU) memakai 9Router AI, lalu mengirim URL baru ke Google PuSH + IndexNow **10 detik setelah publish**.

Versi saat ini: **1.7.0**.

## Pembaruan v1.7.0 — Riset bersumber, memori, dan orkestrasi kognitif

Mengadaptasi pola dari STORM, mem0, Sentence Transformers, LangGraph, dan Instructor tanpa dependency Python:

- Waktu diambil dari pengaturan WordPress. Setiap sumber punya `checked_at`, setiap klaim punya `as_of`; tahun tidak dipaksa masuk ke semua artikel.
- Sumber riset diinput user sebagai JSON berisi URL, judul, tanggal, dan klaim dengan `exact_quote`. Plugin menolak klaim price/stat/rule tanpa `as_of` dan kutipan yang tidak cocok.
- Keluaran AI divalidasi dengan schema + retry terbatas dan pesan error (Instructor-style).
- Alur `research → outline → validate → write` punya state, checkpoint, dan conditional retry (LangGraph-style).
- Memori topik lintas campaign disimpan ADD-only di tabel `wp_lpg_topic_memory`, dengan embedding 9Router `/v1/embeddings`, fallback leksikal, dan penanda risiko duplikat.

Riset sumber primer: [`docs/12-riset-repo-pendukung.md`](docs/12-riset-repo-pendukung.md). Detail implementasi: [`docs/13-kognitif-v1.7.0.md`](docs/13-kognitif-v1.7.0.md).

## Pembaruan v1.6.0 — Smart Cognitive Reasoning & Anti-Halusinasi

Engine kognitif baru menjaga konten tetap tajam dan konsisten:

- **Anchor waktu 2026** disuntik ke semua prompt, sehingga AI tidak menyebut tahun lampau sebagai tahun berjalan.
- **Memori topik jangka panjang** membaca riwayat artikel yang sudah publish (post/page asli + brief `lpgen_item`) dan memakai maksimal 12 judul/anchor/entitas sebagai pengingat sudut pandang segar.
- **Perencanaan outline 2 tahap**: job topik kini menghasilkan `outline`, `pain_points`, dan `target_table` lebih dulu; job artikel menulis berdasarkan arsitektur itu.
- **Identity visual tunggal**: semua gambar AI memakai palet dark navy `#1A1A2E`, orange `#FF6B35`, gold `#FFD166`, gaya editorial technical illustration, 16:9, dan aturan “no text/no logo”. Yang berubah hanya topik, konsep visual, dan subjek utama.
- Gambar hasil AI/Bing dipotong 16:9, diberi grade warna deterministik, serta disimpan sebagai WebP dengan nama deskriptif `content/<article-slug>/<slug>.webp`.

Lihat [`docs/11-kognitif-dan-visual-1.6.0.md`](docs/11-kognitif-dan-visual-1.6.0.md).

## Pembaruan v1.5.3 — Token lebih hemat dan LP tidak gagal karena struktur JSON

Kontrak desain sekarang diterapkan server: AI hanya menulis isi, sedangkan `design_id`, `section_id`, `image_slot`, `hint` dan `layout` diisi otomatis dari kontrak. Ini menghilangkan kegagalan `invalid_json_shape` karena AI lupa atau salah mengetik kunci struktural.

Prompt artikel juga dipangkas (maksimal 20 judul lama, tanpa rencana framework penuh dan catatan entitas). Rencana framework yang kedaluwarsa dibuat ulang otomatis sebelum LP dibuat. Internal link tetap dipasang saat publish, setelah permalink BOFU tersedia.

Lihat [`docs/10-token-dan-lp-1.5.3.md`](docs/10-token-dan-lp-1.5.3.md) untuk analisis log, angka prompt sebelum/sesudah, dan batas pengujian.

## Pembaruan v1.5.2 — Generate + kontrak desain 12 framework

Memperbaiki objek request JavaScript yang dipakai sebelum dibuat, sehingga Generate tidak mengirim permintaan dan antrean tetap 0/0. Ditambahkan pesan gagal yang jelas, progres langsung, serta perbaikan worker, auto-publish dan parsing jadwal tanggal tertentu.

Pada versi yang sama, kontrak desain AIDA digeneralisasi ke 12 preset. AI wajib mengembalikan `design_id`, `section_id` dan `image_slot` sesuai kontrak; AIDA tetap memakai layout khusus, 11 preset lain memakai renderer generik yang sadar slot. Rencana framework lama perlu dibuat ulang karena sidik campaign sekarang memasukkan kode desain.

Lihat [`docs/08-perbaikan-generate-1.5.2.md`](docs/08-perbaikan-generate-1.5.2.md) untuk diagnosis alur dan [`docs/09-kontrak-desain-12-framework.md`](docs/09-kontrak-desain-12-framework.md) untuk daftar kontrak, cara kerja prompt dan langkah regenerate.

## Pembaruan v1.5.1 — Desain pertama: AIDA

AIDA memakai desain khusus dengan ID section dan slot gambar yang divalidasi. Preview HTML membaca data pengguna/hasil campaign tersimpan. Internal link kampanye, pemilih upload per section dan metadata per konten diperkuat.

Lihat [`docs/07-aida-1.5.1.md`](docs/07-aida-1.5.1.md) untuk riwayat kontrak AIDA, penggunaan preview dan standar SEO. Sejak 1.5.2, preset lain juga terikat kontrak; lihat [`docs/09-kontrak-desain-12-framework.md`](docs/09-kontrak-desain-12-framework.md).

## Pembaruan v1.5.0 — HTML dan CSS LP

Renderer HTML WordPress responsif untuk 12 preset dan Framework AI Khusus. Setiap campaign memiliki rencana AI sendiri, dengan urutan langkah yang divalidasi. CSS dasar lengkap dimuat otomatis; CSS tambahan tersedia di Settings → Frameworks. Pilihan canvas halaman penuh mengurangi ketergantungan pada layout tema.

Lihat [`docs/06-html-framework-css.md`](docs/06-html-framework-css.md) untuk pemakaian, CSS, kompatibilitas tema dan batas pengujian. Acuan terbaru untuk struktur dan kontrak LP adalah [`docs/09-kontrak-desain-12-framework.md`](docs/09-kontrak-desain-12-framework.md). Impor banyak keyword belum termasuk rilis ini.

## Pembaruan v1.4.0

Funnel lokal, anchor keyword AI menuju BOFU campaign, 8–12 entitas per konten, dan pemeriksaan duplikat lintas campaign. Hasil edit review dipertahankan dan ketergantungan antrean diperbaiki.

Lihat [`docs/05-funnel-lokal.md`](docs/05-funnel-lokal.md) untuk contoh campaign Solo, aturan terbaru, batasan pemeriksaan dan hasil pengujian. Dokumen ini menjadi acuan untuk perubahan alur v1.4.0.

## Mulai dari mana

- [`RENCANA.md`](RENCANA.md) — rencana induk, keputusan desain, kriteria penerimaan, risiko, hasil verifikasi.
- [`docs/01-konsep-funnel.md`](docs/01-konsep-funnel.md) — arsitektur TOFU/MOFU/BOFU + contoh paket konten.
- [`docs/02-desain-lp.md`](docs/02-desain-lp.md) — desain LP produk / jasa / affiliate + pemetaan 12 framework.
- [`docs/09-kontrak-desain-12-framework.md`](docs/09-kontrak-desain-12-framework.md) — daftar section/slot dan cara AI mengikuti desain yang sudah dibuat.
- [`docs/03-spesifikasi-teknis.md`](docs/03-spesifikasi-teknis.md) — data model, endpoint, keamanan, pengujian.
- [`docs/04-panduan-pakai.md`](docs/04-panduan-pakai.md) — instalasi sampai verifikasi indexing.

## Implementasi

- `lp-generator/` — source plugin, siap di-copy ke `wp-content/plugins/lp-generator/`.
- `lp-generator-1.7.0.zip` — paket siap upload lewat **Plugins → Add New → Upload Plugin**.
- `version.json` — manifest update untuk fitur *one-click update* dari Github.

## Instalasi & update

1. Upload `lp-generator-1.7.0.zip` lewat **Plugins → Add New → Upload Plugin**, lalu aktifkan.
2. Isi kredensial 9Router di **LP Generator → Settings → AI**.
3. Saat ada rilis baru, WordPress akan menampilkan notifikasi **Update now** di halaman **Plugins** — tinggal klik. Manifest yang dibaca plugin terkunci ke `https://raw.githubusercontent.com/marzuki46/LP-generator/main/version.json`, dan paket update diambil dari file ZIP yang ada di repo yang sama. Tidak perlu upload manual lagi.

Untuk merilis versi baru:

1. Naikkan header `Version:` dan konstanta `LPG_VERSION` di `lp-generator/lp-generator.php`.
2. Perbarui `Stable tag` + `Changelog` di `lp-generator/readme.txt`.
3. Perbarui `version.json` (`version`, `package`, `changelog`).
4. Build ulang ZIP dengan nama `lp-generator-<versi>.zip` di root repo.
5. Commit, tag `v<versi>`, lalu `git push --follow-tags`.

## Fitur v1.3.4

- Perbaikan string UI yang ter-encode dua kali (tanda hubung, panah, titik tengah) agar tidak muncul sebagai mojibake.
- Settings bertab memakai gaya `nav-tab` WordPress: ikon, deskripsi tiap tab, active state, kartu responsif, sidebar alat bantu, dan fokus keyboard yang terlihat.
- Settings bertab: AI, Indexing, Frameworks, Content, Amazon, Schedule, Images, Advanced.
- WebSub/PuSH terkunci ke `https://pubsubhubbub.appspot.com/publish` dan aktif by default.
- RSS/WebSub fan-out gratis mengirim ping ke Google WebSub dan Superfeedr; endpoint tambahan bisa ditambahkan per baris di Settings.
- Campaign BOFU dapat memiliki daftar target area, batas akhir push konten, batas akhir internal link, dan mode semua artikel langsung menuju BOFU.
- Produk, jasa, dan afiliasi memakai generator LP yang sama dengan 12 preset: AIDA, PAS, BAB, FAB, QUEST, DAGMAR, DIPADA, IDEPA, ACCA, AISAS, 4P, dan SPIN.
- Kunci IndexNow dibuat otomatis saat aktivasi/save; file `.txt` dibuat otomatis bila root dapat ditulis.
- Framework campaign disusun AI per langkah (nama langkah dan image keyword), lalu server mengikat section hint, layout, image role, section ID dan slot gambar ke kontrak desain; gambar & galeri mengikuti slot yang sudah ditentukan.
- Halaman **Activity Log** interaktif: statistik, filter campaign/level/pencarian, hapus satu baris, hapus massal, hapus semua dengan konfirmasi.
- Catatan progres dicatat untuk awal campaign, framework, job mulai/selesai/retry, fallback engine gambar, permalink publish, dan submit index.
- Update plugin sekali klik langsung dari halaman **Plugins**.

## Verifikasi cepat

```powershell
cd 'D:\Program Marzuki\Plugin\LP Generator\lp-generator'
php tests\run.php
php tests\smoke-bootstrap.php
```

Terakhir dijalankan pada paket v1.7.0: **863 test passed, 0 failed**; `php -l` untuk file yang diubah lolos; bootstrap & hook PASS. Pengujian browser untuk tombol Generate juga PASS. Generate/publish langsung ke WordPress dan 9Router produksi belum diuji pada sesi ini.

## Alur singkat

```
Campaign (produk/jasa/afiliasi)
  → generate framework AI per langkah (server mengikat hint/role ke kontrak desain)
  → generate berurutan: LP → topik MOFU → artikel MOFU → topik TOFU → artikel TOFU
  → Review & approve
  → Publish (BOFU → MOFU → TOFU) + internal link + galeri sesuai section
  → jeda 10 detik
  → Google PuSH + IndexNow
  → Activity Log mencatat setiap progres
```

Sejak v1.2.0, setiap LP dan artikel juga mendapat galeri gambar dari engine pilihan (upload → AI 9Router → Bing Images), dikonversi ke WebP <100 KB dengan metadata keyword. Sejak v1.3.4, penempatan galeri mengikuti `section_hint` yang diputuskan AI.
