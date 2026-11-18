# 03 — Spesifikasi Teknis

## 1. Lingkungan

| Item | Nilai |
|---|---|
| WordPress | minimal 6.4 |
| PHP | minimal 8.1 (dikembangkan & di-lint dengan 8.4.27) |
| Ekstensi PHP | json, mbstring, curl/openssl (untuk `wp_remote_*` WP menyediakan layer HTTP) |
| Namespace | `LPG` |
| Text domain | `lp-generator` |
| Prefix option/meta/hook | `lpgen_` / `_lpgen_` / `lpgen_` |

---

## 2. Data model

### 2.1 Custom Post Types

**`lpgen_campaign`** (private, tidak publicly queryable)

| Meta key | Tipe | Keterangan |
|---|---|---|
| `_lpgen_offer_type` | string | `product` / `service` / `affiliate` |
| `_lpgen_offer_name` | string | Nama produk/jasa/offer |
| `_lpgen_offer_url` | url | URL checkout / kontak / afiliasi |
| `_lpgen_audience` | text | Target audiens |
| `_lpgen_problem` | text | Masalah utama audiens |
| `_lpgen_promise` | text | Hasil yang dijanjikan |
| `_lpgen_benefits` | text | Dipisah baris |
| `_lpgen_price` | string | Harga / start from |
| `_lpgen_bonus` | text | Dipisah baris |
| `_lpgen_guarantee` | string | Garansi |
| `_lpgen_cta` | string | Teks tombol |
| `_lpgen_voice` | string | Tone of voice |
| `_lpgen_language` | string | `id` / `en` |
| `_lpgen_framework` | string | Key framework |
| `_lpgen_mofu_count` | int | 1–10 |
| `_lpgen_tofu_count` | int | 1–10 |
| `_lpgen_status` | string | `draft` / `generating` / `review` / `published` / `failed` |
| `_lpgen_lp_post_type` | string | `page` / `post` |
| `_lpgen_article_post_type` | string | `post` / `page` |
| `_lpgen_publish_status` | string | `publish` / `draft` / `pending` |
| `_lpgen_article_category` | int | Category ID (0 = tidak ada) |
| `_lpgen_index_enabled` | bool | Aktifkan indexer untuk campaign ini |
| `_lpgen_index_delay` | int | Detik jeda, default 10 |

**`lpgen_item`** (private, dipakai sebagai "draft buffer")

| Meta key | Keterangan |
|---|---|
| `_lpgen_campaign_id` | ID campaign induk |
| `_lpgen_stage` | `bofu` / `mofu` / `tofu` |
| `_lpgen_kind` | `lp` / `topic` / `article` |
| `_lpgen_index` | Urutan ke- dalam stage (0-based) |
| `_lpgen_status` | `queued` / `generated` / `approved` / `rejected` / `published` / `failed` |
| `_lpgen_framework` | Framework yang dipakai |
| `_lpgen_structured` | JSON hasil AI (mentah, untuk debug) |
| `_lpgen_seo_title` | SEO title |
| `_lpgen_meta_description` | Meta description |
| `_lpgen_target_stage` | Stage target link (`mofu` / `bofu`) |
| `_lpgen_anchor` | Anchor suggestion |
| `_lpgen_offer_url` | Salinan URL offer (bisa diedit per item) |
| `_lpgen_post_id` | ID post/page hasil publish |
| `_lpgen_permalink` | Permalink hasil publish |
| `_lpgen_model` | Model 9Router yang berhasil |
| `_lpgen_tokens` | Total token |
| `_lpgen_error` | Pesan error terakhir |

Isi artikel/LP disimpan di `post_content` item dalam bentuk blok Gutenberg. Topik disimpan di `post_excerpt` sebagai ringkasan angle + outline.

### 2.2 Tabel custom

Dibuat via `dbDelta` saat aktivasi.

```sql
CREATE TABLE wp_lpgen_jobs (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  campaign_id bigint unsigned NOT NULL,
  item_id bigint unsigned NOT NULL DEFAULT 0,
  kind varchar(32) NOT NULL,
  payload longtext,
  status varchar(16) NOT NULL DEFAULT 'queued',
  attempts tinyint unsigned NOT NULL DEFAULT 0,
  run_at datetime NOT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  last_error text,
  PRIMARY KEY (id),
  KEY campaign_status (campaign_id, status),
  KEY run_at_status (run_at, status)
);

CREATE TABLE wp_lpgen_logs (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  campaign_id bigint unsigned NOT NULL DEFAULT 0,
  job_id bigint unsigned NOT NULL DEFAULT 0,
  level varchar(16) NOT NULL,
  message varchar(500) NOT NULL,
  context longtext,
  created_at datetime NOT NULL,
  PRIMARY KEY (id),
  KEY campaign_id (campaign_id),
  KEY job_id (job_id)
);
```

Semua query memakai `$wpdb->prepare`. `context` hanya berisi data non-rahasia (model, HTTP code, durasi).

---

## 3. Settings (option `lpgen_settings`)

| Key | Default | Keterangan |
|---|---|---|
| `ninerouter_url` | `''` | Base URL 9Router, mis. `http://localhost:3000` |
| `ninerouter_key` | `''` | Bearer token (di-mask di UI) |
| `api_format` | `openai` | `openai` / `anthropic` |
| `models` | `vip,mycodex,openai/gpt-4o-mini` | Fallback chain, dipisah koma |
| `timeout` | `120` | Detik timeout request AI |
| `max_retries` | `2` | Retry per job |
| `temperature` | `0.7` | Sampling |
| `index_enabled` | `1` | Aktifkan indexing |
| `indexnow_key` | `''` | Kunci IndexNow |
| `indexnow_key_location` | `''` | URL key file bukan root (opsional) |
| `indexnow_endpoints` | `https://api.indexnow.org/indexnow` | Dipisah koma |
| `push_enabled` | `1` | Kirim PuSH publish |
| `push_hub` | `https://pubsubhubbub.appspot.com/publish` | Endpoint hub |
| `index_delay` | `10` | Jeda detik setelah publish |
| `custom_frameworks` | `''` | JSON preset tambahan |
| `preserve_data_on_uninstall` | `1` | Jangan hapus post saat uninstall |
| `allowed_role` | `administrator` | Role yang mendapat capability `lpgen_manage` |

---

## 4. 9Router client

### Request (format OpenAI)

```http
POST {ninerouter_url}/v1/chat/completions
Authorization: Bearer {ninerouter_key}
Content-Type: application/json

{
  "model": "vip",
  "messages": [
    {"role":"system","content":"..."},
    {"role":"user","content":"..."}
  ],
  "temperature": 0.7,
  "response_format": {"type":"json_object"}
}
```

### Request (format Anthropic)

```http
POST {ninerouter_url}/v1/messages
anthropic-version: 2023-06-01
Authorization: Bearer {ninerouter_key}
Content-Type: application/json

{
  "model": "cc/claude-...",
  "max_tokens": 4096,
  "system": "...",
  "messages": [{"role":"user","content":"..."}]
}
```

### Fallback

```
for model in models:
    try request(model)
    if 2xx && JSON valid && required fields ada: return
    log attempt (mask key), lanjut model berikutnya
throw AiException
```

Fungsi murni yang diuji tanpa WP:

- `Frameworks::all()`, `Frameworks::get($key)`
- `Prompts::build_lp($ctx)`, `Prompts::build_topic($ctx)`, `Prompts::build_article($ctx)`
- `Utils::mask_secret($value)`, `Utils::parse_model_list($csv)`, `Utils::index_delay($settings)`
- `Indexer::build_push_body($feed_url)`, `Indexer::build_indexnow_payload($url,$key,$loc,$host)`
- `Publisher::build_cta_block($url,$text,$affiliate,$anchor)`

---

## 5. WP-Cron & konkurensi

- Hook: `lpgen_run_job`.
- Satu job dijalankan per event. Di akhir handler, job berikutnya dijadwalkan `time() + 1`.
- Lock: transient `lpgen_job_lock` (5 menit) dilepas di akhir handler / `shutdown`.
- Jika cron mati, tombol **Run queue now** di dashboard menjalankan satu job secara sinkron (AJAX).
- Index job dijadwalkan terpisah pada `publish_time + index_delay` (default 10 detik).
- Event di-unschodule saat campaign dihapus / direset.

---

## 6. Publisher

- Blok yang dipakai: `heading`, `paragraph`, `list`, `table`, `quote`, `buttons`, `separator`, `group`.
- `wp_kses_post()` sebelum `wp_insert_post`.
- LP → `post_type` dari `_lpgen_lp_post_type` (default `page`).
- Artikel → `post_type` dari `_lpgen_article_post_type` (default `post`), kategori opsional.
- `post_status` dari `_lpgen_publish_status` (default `publish`).
- Internal link ditambahkan sebagai paragraf terakhir sebelum FAQ/final CTA:
  - MOFU → permalink LP.
  - TOFU → permalink artikel MOFU pertama yang sudah publish; fallback ke LP.
- Affiliate CTA: `<a rel="sponsored nofollow" ...>`.
- Set publish: update meta item `_lpgen_post_id`, `_lpgen_permalink`, status `published`.

---

## 7. Indexer

### 7.1 PuSH

```php
wp_remote_post($hub, [
  'timeout' => 15,
  'body' => [
    'hub.mode' => 'publish',
    'hub.url'  => get_feed_link('rss2'),
  ],
]);
```

### 7.2 IndexNow

```php
wp_remote_post($endpoint, [
  'timeout' => 15,
  'headers' => ['Content-Type' => 'application/json'],
  'body'    => wp_json_encode([
    'host'        => wp_parse_url(home_url(), PHP_URL_HOST),
    'key'         => $key,
    'keyLocation' => $key_location ?: home_url('/' . $key . '.txt'),
    'urlList'     => [ $permalink ],
  ]),
]);
```

Validasi key: `preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{7,63}$/', $key)`.

### 7.3 Logging

Format baris log:
`[{level}] campaign={id} job={id} {message} | ctx={jsonTanpaRahasia}`

Dilarang mencatat: `ninerouter_key`, `indexnow_key`, isi prompt penuh, konten artikel.

### 7.4 Ekspektasi yang jujur

- IndexNow: push URL nyata ke engine peserta. Bisa 202/200 bila diterima.
- PuSH: memberi tahu hub bahwa feed berubah; **bukan** permintaan indexing URL spesifik dan tidak menjamin Google langsung mengindeks.
- Tidak ada integrasi GSC OAuth di v1.

---

## 8. Keamanan — checklist implementasi

- [x] `ABSPATH || exit` di semua file
- [x] Capability `lpgen_manage` untuk start/publish/regenerate/approve/reject/run-now
- [x] `manage_options` untuk settings
- [x] `check_ajax_referer('lpgen_action', 'nonce')` di semua handler AJAX
- [x] Sanitasi semua `$_POST`/`$_GET`
- [x] Escape semua output
- [x] `$wpdb->prepare` di semua query
- [x] `wp_http_validate_url` untuk URL 9Router & offer URL
- [x] Mask API key di UI & log
- [x] Regex key IndexNow sebelum tulis file
- [x] Single-job lock
- [x] Uninstall tidak menghapus post publik

---

## 9. Rencana pengujian

| Pengujian | Alat | Dijalankan di mesin ini? |
|---|---|---|
| Syntax PHP | `php -l` | Ya |
| Pure logic (framework, prompt, payload, delay) | `php tests/run.php` | Ya |
| WPCS | `phpcs --standard=WordPress` | Tidak terpasang; disarankan di CI/user |
| Integrasi WP nyata | WP 6.4+ lokal | Tidak; harus di situs user |
| Live AI 9Router | kredensial user | Tidak; butuh NINEROUTER_URL/KEY |
| Live IndexNow | key file di domain user | Tidak; butuh domain live |

---

## 10. Ekstensi v1.1.0 — Amazon, entitas, tag, SEO, jadwal

### 10.1 Metadata campaign baru

| Meta key | Tipe | Keterangan |
|---|---|---|
| `_lpgen_phone` | string | No HP/WhatsApp, dipakai placeholder `{{phone}}` |
| `_lpgen_brand` | string | Nama brand, placeholder `{{brand}}` |
| `_lpgen_city` | string | Kota/area layanan, placeholder `{{city}}` |
| `_lpgen_entity_enabled` | bool | Generate entitas keyword via AI |
| `_lpgen_entity_count` | int | 3–15, default 8 |
| `_lpgen_tag_max` | int | 1–3, default 3 |
| `_lpgen_tag_content_enabled` | bool | Buat artikel otomatis per tag |
| `_lpgen_tag_schedule_mode` | string | `after_publish` / `fixed` / `daily` |
| `_lpgen_tag_schedule_delay` | int | Menit, default 60 |
| `_lpgen_tag_schedule_time` | string | `HH:MM`, default `09:00` |
| `_lpgen_auto_publish` | bool | Toggle override auto-publish per campaign |
| `_lpgen_schedule_mode` | string | `immediate` / `interval` / `fixed` / `daily` |
| `_lpgen_schedule_interval` | int | Menit antar post |
| `_lpgen_schedule_start` | string | `YYYY-MM-DDTHH:MM` (timezone situs) |
| `_lpgen_schedule_time` | string | `HH:MM` untuk mode `daily` |
| `_lpgen_schedule_jitter` | int | 0–60 menit acak |
| `_lpgen_amazon_enabled` | bool | Aktifkan produk Amazon untuk campaign ini |
| `_lpgen_amazon_keyword` | string | Override keyword pencarian produk |
| `_lpgen_entities` | JSON | Entitas keyword AI: `{term,type,intent,notes}` |
| `_lpgen_amazon_products` | JSON | Produk hasil scrape (cache per campaign) |
| `_lpgen_image_enabled` | bool | Aktifkan galeri gambar untuk campaign ini |
| `_lpgen_image_source` | string | `auto` / `upload` / `ai` / `bing` |
| `_lpgen_image_upload_ids` | CSV | Attachment ID Media Library untuk engine upload (maks 12) |
| `_lpgen_image_count` | int | 0 = ikut global, jika tidak 1–6 |

Item metadata tambahan: `_lpgen_tags` (JSON, maks 3), `_lpgen_tag` (tag artikel khusus), dan `_lpgen_images` (JSON attachment gambar hasil pipeline). Stage item juga bisa `tag`.

### 10.2 Amazon affiliate (tanpa PA-API)

Mengikuti pola `stupidpie v4`:

1. Query: `{keyword} site:amazon.{region}` ke `https://www.bing.com/images/async`.
2. Parse node `<a class="iusc" m="{json}">`, ambil `murl`, `purl`, `t`, `turl`.
3. Filter domain `amazon.{region}` bila ada; fallback hasil generik bila kosong.
4. Gambar diproksikan: `https://i0.wp.com/{url-tanpa-schema}`.
5. Deeplink: template default `https://www.amazon.{region}/s/ref=nb_sb_noss_2?url=search-alias%3Daps&field-keywords={keyword}&tag={tag}`.
6. Link dirender dengan `rel="sponsored nofollow noopener" target="_blank"`.
7. Cache transient `lpgen_amz_{hash}` sesuai `amazon_cache_hours`.

Region divalidasi dengan allowlist. Scrape memakai `wp_remote_get` + `wp_http_validate_url`, timeout 5–30 detik, batas body 2 MB. Tidak ada URL arbitrary dari user yang di-fetch.

### 10.3 Alur job tambahan

```
amazon_products → generate_entities → generate_lp → topics → articles
                → finalize → [auto_publish?] start_publish
                            → publish jobs ( terjadwal )
                            → generate_tag jobs ( terjadwal )
                            → publish tag jobs
```

- `generate_entities` menyimpan entitas ke campaign sebelum prompt LP/topik/artikel disusun.
- `generate_tag` membuat satu artikel untuk satu tag, lalu menyisipkan job `publish` dengan `run_at` dari jadwal tag.
- `Utils::schedule_timestamp()` mengubah mode jadwal ke UTC timestamp; `run_at` disimpan UTC via `gmdate('Y-m-d H:i:s', ...)`.
- `auto_publish` efektif bila aktif global **atau** per campaign; item `generated` diubah menjadi `approved` saat `finalize_orphans`.

### 10.4 Yoast injection

`Publisher::apply_seo_meta()` menulis:

| Meta key | Sumber |
|---|---|
| `_yoast_wpseo_focuskw` | entitas pertama / judul / tag sesuai `focus_keyword_source` |
| `_yoast_wpseo_title` | `yoast_title_template`, maks 60 char |
| `_yoast_wpseo_metadesc` | `yoast_description_template`, maks 155 char |

Placeholder: `{{keyword}}`, `{{title}}`, `{{tag}}`, `{{brand}}`, `{{city}}`, `{{phone}}`, `{{offer}}`, `{{promise}}`, `{{price}}`, `{{stage}}`, `{{language}}`. Placeholder tidak dikenal dihapus, bukan dibiarkan apa adanya.

### 10.5 Pengujian ekstensi

`tests/test-new-features.php` mencakup template placeholder, sanitasi tag maks 3, mode jadwal, normalize region, query Bing, deeplink afiliasi, Photon proxy, parse HTML Bing, prompt entitas, dan prompt artikel tag.

### 10.6 Pipeline gambar (v1.2.0)

`includes/class-image.php` menangani tiga engine:

| Engine | Sumber | Catatan |
|---|---|---|
| `upload` | Attachment ID pada campaign | Dipilih lewat media picker; dipakai berurutan |
| `ai` | `POST {ninerouter_url}/v1/images/generations?response_format=binary` | Body `{model,prompt,n,size}`, auth Bearer, fallback respons `b64_json`/`url` |
| `bing` | `https://www.bing.com/images/async` | Parser `a.iusc m="{json}"` dipakai ulang dari `Amazon::parse_images()` |

Alur `auto`: `upload → ai → bing`. Satu keyword gagal tidak membatalkan item; error dicatat ke `wp_lpgen_logs`.

Konversi WebP memakai `wp_get_image_editor()` dengan fallback Imagick/GD. Batas ukuran dicek **setelah** IPTC ditanam: quality turun 90→40 (step 10), lalu lebar diperkecil 15% sampai ≤ batas setting (hard cap 99 KB). File yang lolos disimpan di `wp-content/uploads/{Y}/{m}/`, lalu `wp_insert_attachment()` + `wp_generate_attachment_metadata()`.

Keamanan: hanya URL http(s) publik yang diterima (`wp_http_validate_url` + blokir localhost/private IP), download dibatasi 10 MB, timeout dapat diatur, dan kunci API tidak pernah ditulis ke log.

`Publisher::build_blocks()` menyisipkan `wp:gallery` setelah intro/disclosure/produk; `Publisher::publish_item()` memuat ulang `_lpgen_images` karena blok dibangun ulang dari `_lpgen_structured` saat publish. `tests/test-image-pipeline.php` mencakup normalisasi source/size, batas ukuran, keyword, urutan engine, SSRF guard, dan markup galeri.
