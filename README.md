# LP Generator — Workspace

Plugin WordPress yang mengubah satu campaign offer menjadi funnel konten lengkap (TOFU → MOFU → BOFU) memakai 9Router AI, lalu mengirim URL baru ke Google PuSH + IndexNow **10 detik setelah publish**.

Versi saat ini: **1.3.6**.

## Mulai dari mana

- [`RENCANA.md`](RENCANA.md) — rencana induk, keputusan desain, kriteria penerimaan, risiko, hasil verifikasi.
- [`docs/01-konsep-funnel.md`](docs/01-konsep-funnel.md) — arsitektur TOFU/MOFU/BOFU + contoh paket konten.
- [`docs/02-desain-lp.md`](docs/02-desain-lp.md) — desain LP produk / jasa / affiliate + pemetaan 12 framework.
- [`docs/03-spesifikasi-teknis.md`](docs/03-spesifikasi-teknis.md) — data model, endpoint, keamanan, pengujian.
- [`docs/04-panduan-pakai.md`](docs/04-panduan-pakai.md) — instalasi sampai verifikasi indexing.

## Implementasi

- `lp-generator/` — source plugin, siap di-copy ke `wp-content/plugins/lp-generator/`.
- `lp-generator-1.3.6.zip` — paket siap upload lewat **Plugins → Add New → Upload Plugin**.
- `version.json` — manifest update untuk fitur *one-click update* dari Github.

## Instalasi & update

1. Upload `lp-generator-1.3.6.zip` lewat **Plugins → Add New → Upload Plugin**, lalu aktifkan.
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
- Framework campaign dibuat AI per langkah (nama, section hint, image role, image keyword), lalu gambar & galeri mengikuti keputusan AI tersebut.
- Halaman **Activity Log** interaktif: statistik, filter campaign/level/pencarian, hapus satu baris, hapus massal, hapus semua dengan konfirmasi.
- Catatan progres dicatat untuk awal campaign, framework, job mulai/selesai/retry, fallback engine gambar, permalink publish, dan submit index.
- Update plugin sekali klik langsung dari halaman **Plugins**.

## Verifikasi cepat

```powershell
cd 'D:\Program Marzuki\Plugin\LP Generator\lp-generator'
php tests\run.php
php tests\smoke-bootstrap.php
```

Terakhir dijalankan pada paket v1.3.4: **123 test passed, 0 failed**; seluruh file PHP lolos `php -l`; bootstrap & hook PASS (**28 group hook**, 2 callback aktivasi/deaktivasi). Test juga dijalankan ulang dari ZIP yang diekstrak.

## Alur singkat

```
Campaign (produk/jasa/afiliasi)
  → generate framework AI per langkah (hint + role gambar)
  → generate berurutan: LP → topik MOFU → artikel MOFU → topik TOFU → artikel TOFU
  → Review & approve
  → Publish (BOFU → MOFU → TOFU) + internal link + galeri sesuai section
  → jeda 10 detik
  → Google PuSH + IndexNow
  → Activity Log mencatat setiap progres
```

Sejak v1.2.0, setiap LP dan artikel juga mendapat galeri gambar dari engine pilihan (upload → AI 9Router → Bing Images), dikonversi ke WebP <100 KB dengan metadata keyword. Sejak v1.3.4, penempatan galeri mengikuti `section_hint` yang diputuskan AI.
