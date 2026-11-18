# 04 — Panduan Pakai

## 1. Instalasi

1. Copy folder `lp-generator/` ke `wp-content/plugins/lp-generator/`.
2. Di WP Admin → **Plugins** → aktifkan **LP Generator**.
3. Pastikan PHP ≥ 8.1 dan WP ≥ 6.4 (plugin akan menolak aktifasi jika di bawah itu).
4. Buka menu **LP Generator → Settings**.

---

## 2. Settings wajib

### Tab AI (9Router)

| Field | Contoh |
|---|---|
| 9Router Base URL | `http://localhost:3000` atau `https://router.domain.com` |
| 9Router API Key | token dari environment `NINEROUTER_KEY` |
| Format API | `OpenAI` (default) atau `Anthropic` |
| Model fallback | `vip,mycodex,openai/gpt-4o-mini` |
| Timeout | `120` detik |
| Retry | `2` |
| Temperature | `0.7` |

Klik **Test connection** sebelum pakai. Jika combo `vip` tersedia, biarkan sebagai model pertama—combo 9Router sudah melakukan fallback internal.

### Tab Indexing

| Field | Contoh |
|---|---|
| Aktifkan indexing | ✅ |
| IndexNow key | klik **Generate key** lalu **Create key file** |
| Key location | kosongkan jika file di root domain |
| Endpoints | `https://api.indexnow.org/indexnow` |
| Google PuSH | ✅ `https://pubsubhubbub.appspot.com/publish` |
| Jeda setelah publish | `10` detik |

> **Penting:** file `{key}.txt` harus bisa diakses publik di `https://domainanda.com/{key}.txt`. Jika plugin tidak bisa menulis karena permission hosting, upload manual file tersebut via FTP. Tanpa file ini, IndexNow akan menolak (403).

### Tab Frameworks

Pilih framework default (AIDA, PAS, BAB, FAB, QUEST, dll). Bisa menambahkan preset JSON sendiri.

### Tab Gambar per Konten (v1.2.0)

| Field | Contoh | Catatan |
|---|---|---|
| Tambahkan gambar ke setiap konten | ✅ | Galeri masuk LP + artikel MOFU/TOFU + artikel tag |
| Engine gambar | `Otomatis: upload → AI → Bing` | Bisa dipilih salah satu: upload / AI / Bing |
| Jumlah gambar per konten | `2` | 1–6; campaign bisa override |
| Ukuran maksimal per gambar | `95` KB | Hard cap 99 KB; quality + resolusi diturunkan otomatis |
| Lebar maksimal | `1280` px | Dipakai sebelum kompresi |
| Model image 9Router | `gemini/gemini-3-pro-image-preview` | Cek `/v1/models/image` untuk model lain |
| Ukuran generate AI | `1024x1024` | Opsi lain: 1536x1024, 1024x1536, 1792x1024, 1024x1792 |
| Template title/alt/caption/description | `{{keyword}}`, `{{keyword}} — {{offer}}` | Placeholder sama seperti Yoast |
| Tanam keyword ke IPTC | ✅ | Best-effort; butuh Imagick + WebP |

Jika memakai engine **upload**, siapkan gambar di Media Library, lalu pada campaign klik **Pilih / upload dari Media Library** dan simpan campaign. Attachment yang dipilih dipakai berurutan; bila habis, engine `auto` lanjut ke AI lalu Bing.

---

## 3. Membuat campaign

1. **LP Generator → Campaigns → Add New**.
2. Isi judul campaign (mis. "Lumina Serum – Launch Agustus").
3. Isi meta box **Detail Offer**:
   - Tipe offer: **Produk / Jasa / Afiliasi**
   - Nama offer, URL offer
   - Audiens, masalah, janji, benefit (satu per baris)
   - Harga, bonus, garansi, teks CTA
   - Tone, bahasa, framework
   - Jumlah MOFU / TOFU
   - Post type & status publish
4. Klik **Publish/Update** campaign, lalu tombol **Start generation** di meta box.

---

## 4. Memantau generate

Buka **LP Generator → Dashboard**. Akan tampil:

- Progress bar per campaign (job ke berapa dari total).
- Daftar job: `generate_lp`, `generate_topic`, `generate_article`, dll.
- Status tiap item: queued / generated / failed.

Jika WP-Cron tidak jalan (umum di shared hosting), klik **Run queue now** beberapa kali sampai selesai. Alternatif: tambahkan cron server:

```cron
*/1 * * * * curl -s https://domainanda.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

---

## 5. Review

Setelah status campaign berubah menjadi **Review**, buka **LP Generator → Review**.

Untuk tiap item:

- **Approve** → siap dipublish.
- **Reject** → tidak dipublish.
- **Regenerate** → masuk antrean AI lagi dengan prompt yang sama.
- **Edit** → mengubah judul/konten lewat editor WP biasa (post type `lpgen_item` sudah terdaftar di admin dengan UI editor).

Periksa khusus:
- URL CTA benar (untuk afiliasi: sudah link dengan komisi).
- Anchor internal link tidak ambigu.
- Tidak ada klaim berlebihan.

---

## 6. Publish & Index

1. Di halaman Review, klik **Publish & Index**.
2. Plugin akan:
   - Publish **BOFU dulu**, lalu MOFU, lalu TOFU.
   - Menyisipkan internal link MOFU→BOFU dan TOFU→MOFU/BOFU.
   - Menjadwalkan submit indexer **tepat 10 detik setelah setiap publish**.
3. Cek **LP Generator → Logs** untuk melihat hasil PuSH/IndexNow (HTTP code).

---

## 7. Verifikasi indexing manual

| Kanal | Cara cek |
|---|---|
| IndexNow | Bing Webmaster Tools → IndexNow → daftar URL yang diterima |
| Google | Search Console → URL Inspection → Request Indexing (manual, tetap disarankan) |
| Feed discovery | `curl -X POST https://pubsubhubbub.appspot.com/publish -d "hub.mode=publish&hub.url=https://domainanda.com/feed/" -i` → cari HTTP 204/200 |

> Ingat: PuSH mempercepat penemuan feed, bukan permintaan indexing per URL. Untuk prioritas Google, tetap gunakan GSC secara manual.

---

## 8. Tips biaya AI

- Mulai dengan `mofu_count = 1`, `tofu_count = 2` saat uji coba (total 7 request).
- Pakai model murah untuk `generate_topic` dengan mengubah urutan model di Settings, atau tambahkan filter `lpgen_models_for_kind` (disiapkan di kode).
- Token per item ditampilkan di halaman Review.

---

## 9. Troubleshooting

| Gejala | Penyebab | Solusi |
|---|---|---|
| Activation gagal "requires PHP 8.1" | PHP lama | Upgrade PHP |
| Job tidak jalan | WP-Cron mati | Klik Run queue now / tambah cron server |
| AI error 401 | Key salah | Cek Settings → Test connection |
| AI return JSON rusak | Model tidak patuh | Ganti model pertama di fallback chain |
| IndexNow 403 | Key file tidak ada / beda isi | Upload `{key}.txt` ke root |
| IndexNow 429 | Terlalu sering submit | Kurangi frekuensi, hanya submit konten baru |
| PuSH 404 | Feed URL salah | Cek `get_feed_link('rss2')`, pastikan feed aktif |
| CTA afiliasi tidak ada `rel` | Offer URL kosong | Isi URL offer di campaign |

---

## 10. Fitur baru v1.1.0

### 10.1 Data campaign

Selain field lama, isi juga:

- **No HP / WhatsApp**, **Brand / nama usaha**, **Kota / area layanan** — dipakai sebagai placeholder meta SEO.
- **Bahasa**: pilih `id` atau `en` untuk seluruh konten campaign.
- **Generate entitas keyword**: aktif + jumlah 3–15. Entitas dibuat sekali per campaign dan dipakai prompt LP, topik, artikel, serta focus keyword.
- **Maksimal tag per konten**: 1–3. AI diminta menghasilkan tag yang relevan; tag dibersihkan dan di-unikkan.
- **Artikel otomatis per tag**: bila aktif, setiap tag mendapat satu artikel terjadwal yang mengarah ke LP.
- **Auto publish**: centang untuk melewati halaman Review. Bisa juga diaktifkan global di Settings → Jadwal Publish.

### 10.2 Amazon affiliate

1. Settings → **Amazon Affiliate**: aktifkan integrasi, pilih region, isi **Amazon Associate Tag**.
2. Opsional: ubah jumlah produk (1–8), cache (jam), template deeplink, dan timeout.
3. Pada campaign tipe **Afiliasi**, centang **Pakai produk Amazon**. Jika keyword kosong, plugin memakai nama offer.
4. Jalankan generate. Produk & gambar muncul di LP sebagai kartu dengan link afiliasi.

> Plugin tidak memakai Amazon Product Advertising API, jadi tidak perlu AWS credentials. Produk berasal dari hasil pencarian gambar Bing berfilter `site:amazon.{region}`. Karena ini scrape, hasil bisa berubah; gunakan cache dan cek kualitas gambar sebelum publish. Tombol **Test ambil produk Amazon** di Settings membantu validasi.

### 10.3 Jadwal publish

| Mode | Efek |
|---|---|
| Secepatnya | Job publish berurutan dengan jeda ~1 detik |
| Jeda antar post | `schedule_interval` menit per post |
| Tanggal tertentu | Mulai dari `schedule_start` + jeda per post |
| Satu per hari | Satu post per hari pada `schedule_time` |

`schedule_jitter` menambah acakan 0–60 menit. Semua waktu dihitung dari timezone situs WordPress, lalu disimpan sebagai UTC pada antrean job.

### 10.4 Konten tag

Di halaman **Review**, panel atas menampilkan entitas keyword dan tag. Tombol **Generate ulang** membuat ulang entitas; tombol **Buat konten** di samping tag menjadwalkan satu artikel khusus tag itu. Jadwal tag memakai mode `after_publish` (delay menit), `fixed`, atau `daily`.

### 10.5 Yoast & placeholder

Settings → **SEO — Yoast**:

- Centang **Inject meta ke Yoast**.
- Pilih sumber **focus keyword**: entitas AI / judul / tag.
- Isi template, misalnya:
  - Title: `{{keyword}} | {{brand}}`
  - Description: `{{keyword}} di {{city}} — {{promise}}. Hubungi {{phone}} sekarang.`

Contoh untuk jasa: campaign dengan `phone = 0812-3456-7890`, `city = Bandung`, `promise = Dingin dalam 30 menit` menghasilkan deskripsi yang memuat nomor telepon tanpa perlu edit manual.

---

## 11. Uninstall

Saat plugin dihapus, plugin menghapus options, tabel jobs/logs, dan capability. **Post yang sudah terbit tidak dihapus** (default `preserve_data_on_uninstall = 1`). Ubah setting ini jika ingin pembersihan penuh—hati-hati, tidak bisa dibatalkan.
