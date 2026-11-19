# v1.7.2 — Gambar aktif, master prompt final, dan penjelasan konten support

## 1. Kenapa gambar tidak pernah dibuat

Akar masalah ada di `ContentRepository::get_campaign()`:

```php
$context['image_enabled'] = meta kosong -> '' (string)
```

`ImagePipeline::enabled()` melihat kunci `image_enabled` ada tetapi bernilai kosong, sehingga menganggap gambar **dimatikan**. Akibatnya `prepare_for_item()` langsung mengembalikan `array()` sebelum memanggil engine AI/Bing.

Perbaikan:

```php
$context['image_enabled'] = (int) get_meta(..., settings['image_enabled'] ?? 1);
$context['image_count']   = meta > 0 ? meta : settings['image_count'] ?? 2;
```

Jadi campaign baru tanpa meta gambar tetap memakai default global: **aktif, 2 gambar**. Default `save_campaign_meta()` juga diubah dari 0 ke 2.

Jika Anda memang ingin mematikan gambar, gunakan checkbox **Aktifkan gambar di konten** di campaign; jangan mengosongkan jumlah gambar.

## 2. Master prompt visual final

`VisualIdentity::master_prompt()` sekarang memakai struktur persis:

```text
Create a professional editorial technical illustration for a web development and SEO blog.

Topic: [TOPIC]

Visual concept: [DESCRIBE THE MAIN IDEA]

Main subject: [MAIN SUBJECT]

Style:
clean editorial technology illustration, structured composition,
premium SaaS visual language, subtle depth, realistic but simplified,
professional and trustworthy.

Brand direction:
dark navy background #1A1A2E, warm orange accents #FF6B35, small gold highlights #FFD166,
high contrast, restrained color palette, consistent visual identity.

Composition:
16:9 landscape, clear focal subject in the centre or on the right, generous negative space,
suitable for a blog hero image, no clutter.

Do not include:
No text, no letters, no fake logos, no watermark. Unreadable text, random letters,
excessive neon, generic stock-photo style, distorted UI elements.
```

Hanya tiga baris pertama yang berubah per artikel. Palet, gaya, komposisi, dan aturan larangan identik untuk semua gambar.

`ImagePipeline::build_ai_prompt()` mengisi:

- `Topic` dari `topic_title` (fallback `offer_name`/keyword),
- `Visual concept` dari `visual_concept` bila ada, jika tidak dibentuk dari keyword + topik,
- `Main subject` dari keyword slot gambar.

Semua nilai dibersihkan dengan `sanitize_text_field()` dan diperlakukan sebagai data.

## 3. Ukuran WebP

Sebelumnya hard cap 99 KB sering membuat `cannot_encode_webp_under_limit`, terutama untuk ilustrasi 16:9.

Sekarang:

| Pengaturan | Nilai |
|---|---|
| Default | 180 KB |
| Hard cap | 300 KB |
| Quality | diturunkan otomatis 90 → 40 |
| Lebar | diturunkan bertahap bila masih overweight |

Settings → Images → **Ukuran maksimal per gambar (KB)** kini menerima 10–300.

## 4. Kenapa konten support yang Anda lihat tanpa link/gambar

Contoh yang Anda tempel adalah:**Topik (brief)** — keluaran tahap 1.

```text
Judul + deskripsi + outline + Intent + Funnel angle
```

Itu memang bukan artikel lengkap. Tahap 2 (`generate_article`) yang:

- menulis body 4–7 section,
- menyertakan `cta_paragraph` dengan anchor deskriptif,
- membuat/menempelkan gambar sebagai featured thumbnail.

Review UI sekarang melabelinya **Topik (brief)** dan memuat catatan:

> Topik (brief) adalah hasil tahap 1 … Artikel lengkap dibuat oleh job terpisah. Internal link baru muncul setelah BOFU dan artikel dipublish.

**Internal link tidak disisipkan saat generate**, karena URL BOFU belum tentu tersedia. Link dipasang saat **Publish & Index**, setelah LP BOFU published. Jadi alurnya:

```text
Generate → review → setujui → Publish & Index → link aktif + thumbnail tampil
```

Jika setelah publish artikel masih tanpa link, kirim baris Activity Log untuk item/article tersebut agar saya telusuri job publish-nya.

## 5. Verifikasi

```text
php tests\run.php → 875 passed, 0 failed
node tests\test-admin-runner.js → PASS
php -l → No syntax errors
```

Regression baru:

- campaign tanpa meta gambar tetap `image_enabled=1`, `image_count=2`,
- master prompt memuat blok Topic/Visual concept/Main subject dan palet tetap,
- WebP hard cap 300 KB.
