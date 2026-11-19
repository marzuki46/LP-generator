# Smart Cognitive Reasoning & Visual Identity — 1.6.0

## Tujuan

Menjaga tiga hal saat AI menulis artikel dan gambar:

1. **Aktual**: AI tahu tahun berjalan adalah **2026** dan tidak menyebut tahun lampau sebagai “tahun ini”.
2. **Segar**: AI melihat riwayat artikel yang sudah terbit supaya sudut pandangnya tidak mengulang.
3. **Konsisten**: semua gambar tampak seperti satu brand, walaupun topiknya berbeda.

## 1. Anchor waktu

`CognitiveEngine::time_line()` disuntik ke prompt topik, artikel, artikel tag, dan LP:

```text
TEMPORAL ANCHOR: The current year is 2026. Never describe 2025 or earlier as
"this year", "today" or "currently"...
```

Tahun bisa diubah lewat filter `lpgen_active_year`, tetapi default-nya terkunci 2026. AI tetap boleh menyebut tahun lampau bila memang sedang membahas sejarah.

## 2. Memori topik jangka panjang

`CognitiveEngine::topic_memory()` membaca artikel yang sudah **publish**:

- post/page WordPress asli hasil publish plugin (memiliki `_lpgen_item_id` dan `_lpgen_campaign_id`), atau
- item `lpgen_item` yang memang berstatus publish.

Untuk tiap artikel, plugin mengambil:

- judul,
- anchor internal link lama,
- maksimal 3 entitas/term.

Hanya **12 entri terbaru** yang dikirim ke prompt lewat `TOPIC MEMORY`. Ini sengaja dibatasi agar prompt tetap ringan. Aturan duplikat yang sebenarnya tetap dijalankan di PHP oleh `FunnelContent::assert_unique()` terhadap seluruh korpus.

Contoh baris memori:

```text
- Panduan memilih hosting UMKM | anchor: hosting murah | terms: hosting UMKM, website Solo
```

AI diperintahkan memakai memori itu hanya untuk menghindari pengulangan sudut pandang, bukan untuk menyalin kalimat.

## 3. Perencanaan outline 2 tahap

### Tahap 1 — `generate_topic`

AI kini wajib mengembalikan:

```json
{
  "title": "...",
  "angle": "...",
  "intent": "informational|commercial|transactional",
  "keywords": ["..."],
  "outline": ["...", "..."],
  "pain_points": ["...", "..."],
  "target_table": {"columns": ["Opsi", "Biaya"], "rows": [["...", "..."]]},
  "funnel_link_angle": "..."
}
```

`target_table` boleh `null` bila tidak relevan.

### Tahap 2 — `generate_article`

Generator meneruskan `pain_points` dan `target_table` dari topik ke context artikel. Prompt artikel memuat `TOPIC_PLAN` dan memerintahkan:

- jawab pain points sesuai urutan rencana,
- masukkan target table pada section yang paling relevan,
- jangan mengarang baris/klaim tambahan,
- jangan kembali ke template generik.

Dengan demikian struktur diputuskan dulu, baru prose ditulis.

## 4. Identity visual gambar

`VisualIdentity` adalah satu-satunya sumber aturan visual:

| Elemen | Nilai |
|---|---|
| Background | dark navy `#1A1A2E` |
| Accent | orange `#FF6B35` |
| Secondary | gold `#FFD166` |
| Gaya | `editorial technical illustration` |
| Komposisi | 16:9, subjek utama tengah/kanan, negative space |
| Mood | profesional, tenang, modern |
| Format | WebP, 16:9 |
| Larangan | no text, no letters, no fake logos, no watermark, no neon berlebihan, no stock-photo look |

Master prompt ada di `VisualIdentity::master_prompt()`. Hanya tiga input yang berubah per artikel:

```text
Topic: [TOPIC]
Visual concept: [DESCRIBE THE MAIN IDEA]
Main subject: [MAIN SUBJECT]
```

Contoh untuk artikel n8n:

```text
Topic: n8n workflow automation for business processes
Visual concept: A central workflow orchestration node connecting business forms,
CRM, email notifications, approval steps, and analytics dashboards.
Main subject: a single orchestration node with orange workflow paths
```

Semua nilai campaign dibersihkan dengan `sanitize_text_field()` dan diperlakukan sebagai data, bukan instruksi prompt.

## 5. Crop, grade, dan nama file

`ImagePipeline` memanggil master prompt hanya untuk engine `ai`. Untuk gambar AI/Bing:

- hasil dipotong ke rasio 16:9 memakai `VisualIdentity::crop_size()`;
- ukuran kanonik 1280×720 (bisa dinaikkan sampai batas lebar pengaturan);
- grade warna deterministik diterapkan pada jalur GD/Imagick (kontras ringan + sedikit navy);
- disimpan sebagai WebP dengan quality mengikuti batas ukuran yang sudah ada;
- meta attachment menyimpan `_lpgen_visual_path` dan `_lpgen_visual_style`.

Nama ekspor logis:

```text
content/n8n-workflow-automation-business-process/
  n8n-workflow-automation-business-process.webp
```

`VisualIdentity::filename()` menghasilkan path itu. WordPress tetap menyimpan attachment asli di `wp-content/uploads/YYYY/MM`; path di atas adalah konvensi ekspor/arsip yang disimpan di meta, bukan perpindahan file paksa.

## File yang ditambahkan/diubah

- Baru: `includes/class-cognitive-engine.php`
- Baru: `includes/class-visual-identity.php`
- Diubah: `includes/class-prompts.php`
- Diubah: `includes/class-generator.php`
- Diubah: `includes/class-image.php`
- Diubah: `lp-generator.php`, `readme.txt`, `version.json`
- Test: `tests/test-prompts.php`, `tests/test-image-pipeline.php`, `tests/bootstrap.php`

## Verifikasi lokal

```text
php tests\run.php → 843 passed, 0 failed
php tests\smoke-bootstrap.php → PASS
php -l → No syntax errors
Ukuran prompt: LP ~2.136 token, topik ~1.112 token, artikel ~1.369 token
```

## Batasan

- Grade warna hanya diterapkan pada jalur encoder GD/Imagick. Jika WordPress memakai editor lain, crop 16:9 tetap terjadi tetapi grade tidak dijamin identik.
- Reference image/style reference dari penyedia gambar belum dihubungkan; konsistensi saat ini bergantung pada master prompt + palet + crop + grade.
- Belum diuji dengan 9Router live atau WordPress produksi.
- Tahun 2026 adalah konstanta rilis; gunakan filter `lpgen_active_year` bila periode aktif berubah.
