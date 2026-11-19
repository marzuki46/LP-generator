# Token, internal link dan gagal LP — 1.5.3

## Gejala dari log produksi

Log campaign **Jasa Pembuatan Website Solo** menunjukkan tiga hal:

1. Setiap artikel pendukung menelan 8.000–13.000 token (`Item 1168 … 11174 tokens`, dll.), bukan hanya beberapa ribu.
2. Job `generate_lp` 65 gagal tiga kali dengan pesan **“Framework campaign belum siap atau data offer berubah”** tepat setelah framework berhasil dibuat.
3. Setelah framework dibuat ulang, job `generate_lp` 77 gagal tiga kali dengan **invalid_json_shape** pada model `ag/gemini-3.8-flash`.

Tidak ada internal link karena campaign baru sampai status **review**. Penyisipan tautan terjadi saat **publish**, bukan saat generate. Karena LP tidak pernah berhasil dibuat, tidak ada permalink BOFU yang bisa dijadikan target artikel.

## Akar masalah

### 1. Prompt artikel terlalu besar

`Prompts::format_context()` memasukkan semua nilai campaign ke setiap prompt, termasuk:

- `framework_plan` lengkap dengan tujuan tiap langkah dan keyword gambar,
- seluruh `entities` beserta catatannya,
- hingga **150 judul lama** (`FunnelContent::titles()` membatasi 150).

Dengan data uji yang representatif, prompt sebelum perbaikan:

| Prompt | Perkiraan token input |
|---|---:|
| Perencana framework | 3.941 |
| Penulis LP | 4.372 |
| Penulis artikel | 3.894 |

Token yang dilaporkan di log adalah **input + output**. Output artikel memang panjang, tetapi input yang berulang-ulang itulah yang mendorong total ke 11.000+ token per artikel.

### 2. AI diminta mengirim struktur yang seharusnya milik plugin

Sejak 1.5.2, prompt LP mewajibkan `design_id`, `section_id`, `image_slot`, `hint` dan `layout` di dalam JSON AI. Model flash sering menjawab dengan JSON yang valid secara umum tetapi tanpa salah satu kunci tersebut, sehingga `AiClient::has_required_keys()` menolaknya sebagai `invalid_json_shape`.

Ini tidak perlu. Desain sudah ada di server, jadi	struktur harus ditetapkan server—bukan diminta ke AI.

### 3. Rencana framework yang kedaluwarsa menggagalkan LP

`Frameworks::campaign_plan()` menolak rencana jika sidik campaign berubah. Itu benar untuk keamanan, tetapi LP job sebelumnya langsung gagal setelah tiga percobaan tanpa mencoba menyusun ulang rencana. Padahal, generate ulang framework adalah tindakan yang tepat ketika data offer berubah.

## Perbaikan di 1.5.3

### Struktur diisi server

`DesignContract::bind_content()` kini menimpa/menetapkan, berdasarkan urutan kontrak:

- `design_id`,
- `section_id`,
- `image_slot`,
- `hint`,
- `layout`,
- nomor `step`.

AI cukup mengembalikan `sections[].heading`, `body`, `bullets`, `cards`, `table` dan `quote`. Prompt tidak lagi meminta kunci struktural, dan `design_id` tidak lagi masuk daftar `required`.

Validator tetap memeriksa semuanya setelah binding. Jadi AI tidak bisa memindahkan konten ke section lain atau memakai slot asing.

### Prompt pendukung dirampingkan

`Prompts::format_lean_context()` dipakai untuk artikel, topik, entity dan tag article:

- tidak mengirim `framework_plan` penuh,
- tidak mengirim catatan `entities` (daftar istilah tetap dikirim lewat `entity_line()`),
- maksimal **20 judul lama** yang diambil secara berkala.

Pemeriksaan duplikat yang sebenarnya tetap memindai seluruh korpus di PHP melalui `FunnelContent::assert_unique()`. Jadi penghematan token tidak melemahkan aturan anti-duplikat.

Hasil pengukuran setelah perbaikan:

| Prompt | Sebelum | Sesudah | Penurunan |
|---|---:|---:|---:|
| Penulis LP | 4.372 | 2.351 | ~46% |
| Penulis artikel | 3.894 | 1.392 | ~64% |

### Framework kedaluwarsa dibuat ulang otomatis

Sebelum membuat prompt LP, generator sekarang memeriksa `framework_plan`. Bila kosong/tidak cocok, ia menjalankan `generate_framework()` sekali, memuat ulang campaign, lalu melanjutkan. Bila tetap gagal, pesan kesalahan menyertakan alasan dari proses regenerate.

### Diagnostik JSON lebih jelas

`invalid_json_shape` sekarang menyebut:

- kunci `required` yang hilang, atau
- pesan `json_last_error_msg()` bila decoding gagal.

Ini membedakan “model lupa `faq`” dengan “JSON terpotong/terdapat koma berlebih”.

## Internal link

Alurnya tetap:

```text
generate artikel → review → approve → publish
                                      ↓
                    BOFU sudah publish → link disisipkan ke paragraf CTA
```

`Publisher::insert_funnel_link()` mencari anchor deskriptif dari AI di dalam paragraf yang sudah disetujui, lalu menggantinya dengan `<a href="permalink BOFU">`. Jika anchor tidak ditemukan, link ditambahkan sebagai paragraf penutup. Target di luar situs ditolak oleh `SeoStandards::assert_internal()`.

Jadi, **ketiadaan link saat review bukan bug**. Link baru muncul setelah BOFU terbit dan artikel dipublish. Pastikan LP berhasil dibuat dan disetujui lebih dulu.

## Verifikasi lokal

```text
php tests\run.php
→ 825 passed, 0 failed

php tests\smoke-bootstrap.php
→ PASS plugin bootstrapped; 39 hook groups

php -l (file PHP yang diubah)
→ No syntax errors detected
```

Regression baru:

- `bind_content()` menetapkan dan menimpa ID struktural.
- Prompt tidak lagi mewajibkan `design_id`.
- Prompt pendukung tidak mengirim rencana penuh dan memotong daftar judul lama.

## Batasan

- Pengukuran token memakai panjang prompt dibagi empat, bukan tokenizer resmi 9Router. Angka produksi bergantung pada model dan panjang output.
- Perbaikan ini belum diuji dengan kredensial 9Router live atau WordPress produksi.
- Setelah paket 1.5.3 dipasang, jalankan sekali **Siapkan framework campaign** dan generate ulang LP yang gagal, lalu approve dan publish BOFU sebelum artikel.
