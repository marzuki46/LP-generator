# RENCANA — LP Generator (WordPress Plugin)

**Versi dokumen:** 0.2
**Tanggal:** 17 November 2026
**Lokasi kerja:** `D:\Program Marzuki\Plugin\LP Generator`
**Status:** v1.0.0 selesai; ekstensi v1.1.0 dan v1.2.0 diimplementasikan serta diverifikasi lokal.

---

## 1. Hasil yang ingin dicapai

Sebuah plugin WordPress yang, dari satu form "campaign" (offer), menghasilkan **satu funnel konten lengkap**:

- **1 Landing Page (BOFU)** untuk penawaran **produk**, **jasa**, atau **afiliasi**.
- **N artikel MOFU** + **M artikel TOFU** yang diposisikan sebagai konten edukatif dan secara konsisten mengarahkan pembaca ke LP/BOFU.
- **Topik TOFU dan MOFU juga dihasilkan oleh plugin**, bukan diinput manual.
- Seluruh konten dibuat **satu per satu dengan AI (9Router)**, berurutan otomatis, lalu **ditinjau di akhir** sebelum dipublish.
- Setelah setiap konten dipublish, plugin **jeda 10 detik**, lalu mengirim sinyal indexing: **Google PubSubHubbub (PuSH/WebSub) + IndexNow (multi-search-engine)**.

---

## 2. Keputusan yang sudah dikunci (dari wawancara)

| Keputusan | Pilihan |
|---|---|
| Bentuk aplikasi | WordPress plugin (PHP), di-install langsung di WP target |
| Target publish | Langsung ke WP tempat plugin aktif (`wp_insert_post`) |
| Provider AI | 9Router multi-model + auto-fallback |
| Framework copywriting | Semua framework populer sebagai preset + preset custom |
| Alur generate | Otomatis berurutan, review di akhir |
| Indexer | Google PuSH (RSS) + IndexNow + bot multi-search-engine, jeda 10 detik |

### Asumsi desain ( perlu dikonfirmasi bila berbeda )
1. **Publish bukan otomatis di akhir generate.** Setelah generate selesai, user masuk halaman Review, approve/edit/discard item, lalu klik **Publish & Index**. Alasan: jawaban "review di akhir" dipahami sebagai review sebelum publish. Jika diinginkan auto-publish, tinggal tambahkan opsi `auto_publish = yes` di pengaturan.
2. Jumlah default per campaign: **2 artikel MOFU + 3 artikel TOFU** (bisa diubah per campaign).
3. LP dipublish sebagai **Page**, artikel TOFU/MOFU sebagai **Post**. Bisa diubah di pengaturan.
4. Bahasa default konten: **Bahasa Indonesia**, dengan opsi English per campaign.
5. Satu campaign = satu funnel = satu offer. Multi-offer dibuat sebagai beberapa campaign.

---

## 3. Non-goal (sengaja tidak dikerjakan di versi 1)

- Tidak membuat desain visual landing page dengan drag-and-drop builder / page builder pihak ketiga. LP dirender sebagai **Gutenberg core blocks** agar kompatibel dengan theme apa pun.
- Tidak menghasilkan gambar, video, atau audio. Teks + struktur blok + internal link saja.
- Tidak menjanjikan "instant index Google". Google **tidak punya** push API indexing umum untuk halaman biasa; PuSH hanya mempercepat **discovery feed**, IndexNow adalah push resmi untuk engine yang berpartisipasi. Hal ini dicatat jujur di dokumen teknis.
- Tidak menghubungkan Google Search Console OAuth (API-nya terbatas untuk JobPosting/LiveStream). Tombol manual ke GSC cukup disediakan di panduan pakai.
- Tidak menyimpan kunci API dalam file source, log, atau prompt.

---

## 4. Arsitektur funnel (TOFU → MOFU → BOFU)

```
TOFU (3 artikel)   "Apa itu X?", "Panduan memilih X", "Kesalahan umum X"
        │  internal link (anchor kontekstual, di paragraf penutup)
        ▼
MOFU (2 artikel)   "Perbandingan X", "Studi kasus / cara kerja X", "Review mendalam X"
        │  internal link ke LP
        ▼
BOFU (1 LP)        Landing page produk / jasa / afiliasi → CTA
```

Aturan internal link:
- **MOFU → BOFU** WAJIB, anchor disarankan AI lalu divalidasi admin saat review.
- **TOFU → MOFU** WAJIB bila ada MOFU terbit; fallback **TOFU → BOFU** bila tidak ada.
- Link disisipkan sebagai paragraf CTA di akhir artikel, bukan disembunyikan.
- LP tidak mengandung link funnel keluar selain CTA offer.

Detail lengkap: [`docs/01-konsep-funnel.md`](docs/01-konsep-funnel.md)

---

## 5. Desain Landing Page per tipe offer

| Tipe | Struktur utama | Framework default |
|---|---|---|
| **Produk** | Hero → social proof → problem → solution (FAB) → benefit grid → spesifikasi → testimoni → price stack → bonus → guarantee → FAQ → final CTA | AIDA / FAB |
| **Jasa** | Hero outcome → kredibilitas → pain (PAS) → proses 3–5 langkah → studi kasus → paket layanan → FAQ → CTA konsultasi | PAS / QUEST |
| **Afiliasi** | Review jujur → disclosure → ringkasan verdict → comparison table → pro/kontra → siapa cocok/tidak → bonus → CTA afiliasi → FAQ | PAS / BAB |

Semua struktur di atas diterjemahkan AI menjadi blok Gutenberg. CTA afiliasi otomatis memakai `rel="sponsored nofollow"` dan menyisipkan blok disclosure.

Detail lengkap + contoh prompt: [`docs/02-desain-lp.md`](docs/02-desain-lp.md)

---

## 6. Framework copywriting yang didukung

Preset bawaan: **AIDA, AISAS, DAGMAR, DIPADA, IDEPA, ACCA, PAS, BAB, FAB, QUEST, 4P (Picture–Promise–Prove–Push), SPIN**. Selain itu tersedia slot **custom** berupa JSON steps yang bisa ditambahkan di Settings.

Tiap preset menyimpan: `label`, `steps[]`, `best_for[]`, `section_hint[]`. AI menerima nama + langkah framework dan diminta memetakan setiap langkah menjadi blok konten, bukan sekadar menulis kata kunci.

---

## 7. Alur kerja sistem (urutan eksekusi)

```
[Admin] Isi campaign → klik "Start generation"
   │
   ▼
[Plugin] Buat antrean job (urutan ketat):
   1. generate_lp
   2. generate_topic MOFU #1..#N
   3. generate_article MOFU #1..#N  (pakai topik hasil job 2)
   4. generate_topic TOFU #1..#M
   5. generate_article TOFU #1..#M  (pakai topik hasil job 4)
   │
   ▼
[WP-Cron] Jalankan SATU job per eksekusi → job berikutnya dijadwalkan +1 detik
   │  (AI dipanggil satu request per konten; retry 2x jika gagal)
   ▼
[Campaign] status = review → notifikasi admin
   │
   ▼
[Admin] Halaman Review: approve / edit / reject / regenerate per item
   │
   ▼
[Admin] klik "Publish & Index"
   │
   ▼
[Plugin] Publish BOFU → MOFU → TOFU (wp_insert_post, blok Gutenberg)
   │  tiap publish → schedule index job pada time() + 10 detik
   ▼
[Index job] PuSH publish feed + IndexNow POST → log hasil (tanpa API key)
```

---

## 8. Integrasi AI — 9Router

- Endpoint: `POST {NINEROUTER_URL}/v1/chat/completions` (format OpenAI, default) atau `/v1/messages` (format Anthropic, opsi).
- Model/combo default: `vip` → `mycodex` → `openai/gpt-4o-mini` (bisa diubah di Settings). Combo 9Router melakukan fallback internal; plugin menambahkan fallback lapis kedua bila seluruh combo gagal.
- Setiap request meminta **JSON murni** (`response_format: json_object`) dengan skema per jenis item:
  - `lp`: title, seo_title, meta_description, sections[], cta_text, cta_url, faq[]
  - `topic`: title, angle, intent, keywords[], outline[], funnel_link_angle
  - `article`: title, seo_title, meta_description, intro, sections[], cta_paragraph, anchor_suggestion, faq[]
- Validasi: JSON decoded → cek field wajib → bila tidak valid, retry dengan prompt perbaikan (maks. 2x) → item ditandai `failed`, antrean tetap lanjut.
- Token usage dicatat per item (bukan konten prompt) untuk transparansi biaya.

---

## 9. Integrasi indexing (jeda 10 detik)

| Kanal | Endpoint / metode | Catatan |
|---|---|---|
| Google discovery | `POST https://pubsubhubbub.appspot.com/publish`, body `hub.mode=publish&hub.url={feed_url}` | Feed WP harus mengiklankan `<atom:link rel="hub" href="https://pubsubhubbub.appspot.com">`; WP core sudah melakukannya. Ini **discovery feed**, bukan request indexing per URL. |
| IndexNow | `POST https://api.indexnow.org/indexnow`, JSON `{host, key, keyLocation, urlList:[permalink]}` | Key file `{key}.txt` harus tersedia publik di root site. Plugin bisa membuatkan file dengan validasi nama key ketat `[A-Za-z0-9][A-Za-z0-9-]{7,63}`. |
| Fan-out engine | Opsi endpoints: Bing / Yandex / Naver / Seznam / Yep | Default mati karena global endpoint sudah membagikan ke engine peserta. |
| Ping legacy | Pingomatic/dll | Default mati; kebanyakan sudah tidak reliable. |

Respons dicatat: HTTP code + 200 karakter pertama body. **API key tidak pernah dicatat.**

---

## 10. Keamanan (constraint desain)

- Semua aksi admin: `current_user_can('lpgen_manage')` + `wp_verify_nonce`.
- Settings: `manage_options`, Settings API + `sanitize_callback`.
- Input: `sanitize_text_field`, `sanitize_textarea_field`, `esc_url_raw`, `wp_kses_post`.
- Output: `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`.
- Query: `$wpdb->prepare` untuk semua akses tabel jobs/logs.
- URL 9Router divalidasi dengan `wp_http_validate_url` (mitigasi SSRF dari admin).
- Kunci APIDisimpan di option, ditampilkan ter-mask di UI, tidak masuk log/prompt.
- Pembuatan file key IndexNow: nama divalidasi regex sebelum `file_put_contents` (mitigasi path traversal).
- Satu job lock via transient untuk mencegah eksekusi ganda.
- `uninstall.php`: hapus options/tables/caps; **tidak** menghapus post yang sudah terbit (default `preserve_data = yes`).

---

## 11. Kriteria penerimaan (terukur)

| # | Kriteria | Cara verifikasi |
|---|---|---|
| AC-1 | Plugin aktif di WP 6.4+ / PHP 8.1+ tanpa fatal error | Activation hook cek versi; `php -l` semua file |
| AC-2 | Campaign product/service/affiliate menghasilkan tepat 1 LP + N MOFU + M TOFU | Hitung `lpgen_item` per stage setelah generate |
| AC-3 | Topik MOFU/TOFU dibuat AI, bukan input manual | Job `generate_topic` ada di tabel jobs & item bertipe topic |
| AC-4| Generate berurutan, satu AI request per item | Log job shows sequence + one model call per item |
| AC-5 | Review menampilkan semua item sebelum publish | Item berstatus `generated`, belum ada post publik |
| AC-6 | Publish menghasilkan post/page dengan blok Gutenberg valid | `parse_blocks()` tidak empty; `post_status` sesuai setting |
| AC-7 | Internal link MOFU→BOFU dan TOFU→MOFU/BOFU ada | Assert href di `post_content` |
| AC-8 | Index job dijadwalkan tepat +10 detik setelah publish | `wp_next_scheduled - publish_time == 10` (assert di test harness) |
| AC-9 | Payload PuSH & IndexNow sesuai spesifikasi | Unit test pure builder (tanpa WP) |
| AC-10 | Tidak ada nonce/capability yang terlewat pada 6 aksi AJAX | Static check + manual review |
| AC-11 | Kunci API tidak muncul di log | Logger hanya mencatat masked value |

---

## 12. Fase implementasi (inkremen kecil)

1. **Fondasi** — file header, activator/deactivator/uninstall, CPT, tabel DB, settings.
2. **Konten & AI** — frameworks, prompts, 9Router client, generator queue.
3. **Publish & funnel** — publisher, internal link, blok per tipe offer.
4. **Indexing** — delay 10 detik, PuSH, IndexNow, logging.
5. **Admin UX** — metabox campaign, dashboard progress, review page, JS polling.
6. **Verifikasi** — `php -l`, test harness pure-PHP, checklist keamanan, dokumen pakai.

---

## 13. Peta file

```
LP Generator/
├── RENCANA.md                         ← dokumen ini
├── docs/
│   ├── 01-konsep-funnel.md
│   ├── 02-desain-lp.md
│   ├── 03-spesifikasi-teknis.md
│   └── 04-panduan-pakai.md
└── lp-generator/                      ← folder plugin (siap di-zip / di-copy ke wp-content/plugins)
    ├── lp-generator.php
    ├── uninstall.php
    ├── readme.txt
    ├── includes/
    │   ├── class-plugin.php
    │   ├── class-activator.php
    │   ├── class-deactivator.php
    │   ├── class-database.php
    │   ├── class-frameworks.php
    │   ├── class-prompts.php
    │   ├── class-ai-client.php
    │   ├── class-generator.php
    │   ├── class-publisher.php
    │   ├── class-indexer.php
    │   ├── class-content-repository.php
    │   └── class-utils.php
    ├── admin/
    │   ├── class-admin.php
    │   ├── class-settings.php
    │   ├── class-campaign-ui.php
    │   ├── class-review-ui.php
    │   ├── class-ajax.php
    │   ├── css/admin.css
    │   └── js/admin.js
    ├── languages/lp-generator.pot
    └── tests/
        ├── bootstrap.php
        ├── run.php
        ├── test-frameworks.php
        ├── test-prompts.php
        └── test-indexer-payload.php
```

---

## 14. Risiko & mitigasi

| Risiko | Mitigasi |
|---|---|
| AI mengembalikan JSON rusak | Retry + prompt repair + status failed per item, antrean tetap jalan |
| WP-Cron tidak jalan di shared hosting | Tombol "Run now" di dashboard + petunjuk cron server di panduan |
| IndexNow key file tidak bisa ditulis | Plugin cek `is_writable(ABSPATH)` dan tampilkan instruksi upload manual |
| Biaya AI membengkak | Count default kecil; token usage dicatat; model murah sebagai fallback |
| Konten AI mengandung klaim berlebihan (khusus afiliasi) | Prompt mewajibkan disclosure + hindari klaim garansi tanpa bukti; admin tetap review |
| Duplikat konten TOFU | Prompt menyertakan daftar judul/topik yang sudah dibuat dalam campaign |

---

## 15. Verifikasi yang sudah dijalankan

| Check | Perintah | Hasil |
|---|---|---|
| Syntax PHP | `php -l` pada 25 file `.php` | 25/25 lolos, 0 error |
| Logika murni | `php tests/run.php` | **41 passed, 0 failed** |
| Bootstrap & hook | `php tests/smoke-bootstrap.php` | PASS — 20 grup hook + activation/deactivation terdaftar |
| Static input audit | grep `$_POST` / `$_GET` | Semail sudah `absint` / `sanitize_key` / nonce / capability |
| Paket install | `Compress-Archive` | `lp-generator-1.0.0.zip` (48.451 byte) |

**Keterbatasan verifikasi:** tidak ada instalasi WordPress, kredensial 9Router, atau domain live di mesin ini. Uji integrasi penuh (generate AI nyata, `wp_insert_post`, publish, dan penerimaan IndexNow/PuSH) harus dilakukan di situs WP target. Ini tidak diklaim sudah terverifikasi di sini.

---

## 16. Langkah berikutnya setelah dokumen ini

1. Implementasi kode plugin sesuai peta file.
2. Jalankan verifikasi lokal.
3. User meng-copy folder `lp-generator/` ke `wp-content/plugins/` di situs WP nyata, aktifkan, isi Settings, lalu uji satu campaign.

---

## 17. Ekstensi v1.1.0 (permintaan lanjutan)

### 17.1 Keputusan hasil konfirmasi

| Topik | Keputusan |
|---|---|
| Publish | Tetap ada Review; ditambah opsi `auto_publish` (global + per campaign), default mati |
| "Setingan data" | Dua bagian: pengaturan sumber data Amazon (region, tag, cache, template, timeout) **dan** data campaign (no HP, brand, kota) untuk placeholder meta |
| Bahasa | Satu campaign = satu bahasa, pilih ID atau EN (tidak generate dua set) |
| SEO & tag | Focus keyword + meta title/description via template placeholder; tag maks 3; tiap tag bisa dibuatkan artikel terjadwal |

### 17.2 File baru / diubah

- Baru: `includes/class-amazon.php`, `tests/test-new-features.php`.
- Diubah: `class-utils.php`, `class-prompts.php`, `class-content-repository.php`, `class-publisher.php`, `class-generator.php`, `class-activator.php`, `class-settings.php`, `class-campaign-ui.php`, `class-review-ui.php`, `class-ajax.php`, `admin/js/admin.js`, `admin/css/admin.css`, `lp-generator.php`, `readme.txt`, `docs/03`, `docs/04`, `tests/bootstrap.php`, `tests/smoke-bootstrap.php`, `tests/test-prompts.php`.

### 17.3 Acceptance criteria

1. Campaign affiliasi dengan Amazon aktif menghasilkan kartu produk bergambar + link afiliasi bertanda `sponsored nofollow`.
2. Entitas keyword dihasilkan AI sekali per campaign dan muncul di prompt serta focus keyword Yoast.
3. Tiap konten memiliki maksimal 3 tag unik; tombol per-tag menjadwalkan satu artikel.
4. Meta Yoast berisi focus keyword, title ≤60 char, description ≤155 char dengan placeholder `{{phone}}` terisi.
5. Jadwal publish mendukung immediate, interval, fixed, dan daily; artikel tag mengikuti jadwal tersendiri.
6. `auto_publish` aktif membuat item langsung publish tanpa klik Review.
7. Semua test lokal lolos dan paket zip v1.1.0 terbentuk.

### 17.4 Verifikasi lokal v1.1.0

| Pemeriksaan | Perintah | Hasil |
|---|---|---|
| Syntax | `php -l` untuk seluruh `.php` | 28 file, 0 error |
| Unit | `php tests/run.php` | 68 passed, 0 failed |
| Bootstrap | `php tests/smoke-bootstrap.php` | lolos, 23 group hook |
| Paket | `Compress-Archive` | `lp-generator-1.1.0.zip` |

**Belum terverifikasi:** integrasi WP nyata, 9Router live, Bing Images live, dan IndexNow live — sama seperti batasan di bagian 15.

---

## 18. Ekstensi v1.2.0 — gambar per konten

### 18.1 Keputusan

| Topik | Keputusan |
|---|---|
| Sumber | Semua engine disiapkan; user memilih `auto` (upload → AI → Bing), `upload`, `ai`, atau `bing` per global/campaign |
| Penempatan | Galeri di semua konten publik (LP, artikel MOFU/TOFU, artikel tag) + attachment tersimpan di Media Library |
| Metadata | WordPress title/alt/caption/description dari keyword + IPTC keyword (best-effort via Imagick) |
| Ukuran | WebP, hard cap <100 KB (default 95 KB); quality diturunkan 90→40 lalu resolusi diperkecil 15% per putaran |
| Topic item | Tidak dibuatkan gambar karena tidak dipublish (planning internal) |

### 18.2 File baru / diubah

- Baru: `includes/class-image.php`, `tests/test-image-pipeline.php`.
- Diubah: `lp-generator.php`, `class-content-repository.php`, `class-generator.php`, `class-publisher.php`, `class-activator.php`, `admin/class-settings.php`, `admin/class-campaign-ui.php`, `admin/js/admin.js`, `tests/bootstrap.php`, `readme.txt`, `README.md`, `docs/04-panduan-pakai.md`.

### 18.3 Acceptance criteria

1. Tiap LP/artikel yang dipublish memiliki blok `wp:gallery` berisi attachment WebP.
2. File WebP hasil konversi ≤ batas setting dan selalu <100 KB, termasuk setelah IPTC ditanam.
3. Metadata attachment (title, alt, caption, description) terisi dari keyword/entitas/tag dengan placeholder campaign.
4. Engine upload memakai attachment ID yang dipilih di campaign; engine AI memakai `/v1/images/generations` 9Router; engine Bing memakai parser `a.iusc` yang sama dengan modul Amazon.
5. URL gambar divalidasi http(s) publik; loopback/private IP ditolak; download dibatasi 10 MB.
6. Semua test lokal lolos dan paket zip v1.2.0 terbentuk.

### 18.4 Verifikasi lokal v1.2.0

| Pemeriksaan | Perintah | Hasil |
|---|---|---|
| Syntax | `php -l` untuk seluruh `.php` | 30 file, 0 error |
| Unit | `php tests/run.php` | 92 passed, 0 failed |
| Bootstrap | `php tests/smoke-bootstrap.php` | lolos, 23 group hook |
| Paket | `Compress-Archive` | `lp-generator-1.2.0.zip` |

**Belum terverifikasi:** generate AI 9Router live, scraping Bing Images live, dukungan WebP/Imagick di hosting target, dan tampilan galeri di WP nyata.
