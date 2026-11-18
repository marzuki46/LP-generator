# LP Generator — Workspace

Proyek plugin WordPress untuk menghasilkan funnel konten lengkap (TOFU → MOFU → BOFU) dari satu campaign offer, memakai 9Router AI, lalu submit URL ke Google PuSH + IndexNow **10 detik setelah publish**.

## Mulai dari mana

- [`RENCANA.md`](RENCANA.md) — rencana induk, keputusan desain, kriteria penerimaan, risiko, hasil verifikasi.
- [`docs/01-konsep-funnel.md`](docs/01-konsep-funnel.md) — arsitektur TOFU/MOFU/BOFU + contoh paket konten.
- [`docs/02-desain-lp.md`](docs/02-desain-lp.md) — desain LP produk / jasa / affiliate + pemetaan 12 framework.
- [`docs/03-spesifikasi-teknis.md`](docs/03-spesifikasi-teknis.md) — data model, endpoint, keamanan, pengujian.
- [`docs/04-panduan-pakai.md`](docs/04-panduan-pakai.md) — instalasi sampai verifikasi indexing.

## Implementasi

- `lp-generator/` — source plugin, siap di-copy ke `wp-content/plugins/lp-generator/`.
- `lp-generator-1.2.0.zip` — paket siap upload lewat Plugins → Add New → Upload Plugin.

## Verifikasi cepat

```powershell
cd 'D:\Program Marzuki\Plugin\LP Generator\lp-generator'
php tests\run.php
php tests\smoke-bootstrap.php
```

Terakhir dijalankan: **92 test passed, 0 failed**; **30 file PHP lolos `php -l`**; bootstrap & hook PASS (23 group hook).

## Alur singkat

```
Campaign (produk/jasa/afiliasi)
  → generate otomatis berurutan: LP → topik MOFU → artikel MOFU → topik TOFU → artikel TOFU
  → Review & approve
  → Publish (BOFU → MOFU → TOFU) + internal link
  → jeda 10 detik
  → Google PuSH + IndexNow
```

Sejak v1.2.0, setiap LP dan artikel juga mendapat galeri gambar dari engine pilihan (upload → AI 9Router → Bing Images), dikonversi ke WebP <100 KB dengan metadata keyword.
