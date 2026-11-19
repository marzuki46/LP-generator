# Cognitive Upgrade v1.7.0 — Sumber, Memori, Orkestrasi

Riset pola dari 5 repo ada di [`12-riset-repo-pendukung.md`](12-riset-repo-pendukung.md). File ini menjelaskan apa yang benar-benar dipasang di plugin.

## 1. Waktu dari WordPress, bukan tahun dipaksa

`ResearchSource::anchor()` mengambil:

- `current_time('timestamp')` dari WordPress,
- timezone dari `wp_timezone_string()`,
- tahun aktif dari filter `lpgen_active_year` (default mengikuti WP).

Prompt kini berisi:

```text
TIME ANCHOR (from WordPress settings): <datetime> (<timezone>).
Use this only to interpret checked_at, as_of and dates inside supplied sources.
Do not force the year into every article.
```

Jadi v1.6.0 yang menyisipkan “current year is 2026” ke semua prompt sudah diganti.

## 2. Sumber riset dari user

Field campaign **Sumber Riset (JSON)** menerima:

```json
{
  "sources": [
    {
      "url": "https://contoh.com/panduan-harga",
      "title": "Panduan Harga",
      "publisher": "Contoh Publisher",
      "published_at": "2026-10-12",
      "claims": [
        {
          "type": "price",
          "text": "Kisaran biaya website UMKM",
          "as_of": "2026-10-12",
          "exact_quote": "Kutipan persis dari sumber."
        }
      ]
    }
  ]
}
```

Aturan:

- URL harus http(s) publik.
- `type` boleh `price`, `statistic`, `rule`, `date`, `quote`.
- `price`, `statistic`, `rule` wajib punya `as_of`.
- `text` dan `exact_quote` wajib ada.
- `checked_at` diisi otomatis dari WP bila kosong.
- Klaim dengan `as_of` tahun sebelum tahun WP ditandai basi dan tidak dikirim ke prompt.
- `ResearchSource::quote_exists()` menolak kutipan rekaan.

Prompt artikel hanya boleh memakai klaim bersumber dan wajib menyertakan marker `[n]`. Plugin yang merender daftar sumber; AI tidak membuat references section.

## 3. Structured output ala Instructor

`StructuredOutput` menyediakan:

- `topic_schema()` dan `article_schema()`,
- `validate()` dengan cek required, tipe, panjang, jumlah item, enum, dan `table_or_null`,
- `verify_quotes()` secara rekursif mencari `exact_quote` dan memverifikasi terhadap sumber user,
- `generate_validated()` dengan re-ask maksimal 1 retry di Generator (total masih dibatasi AiClient).

Ketika validasi gagal setelah retry, job dihentikan dengan pesan field yang gagal—bukan konten cacat yang dipublish.

## 4. Orkestrasi ala LangGraph

`Orchestrator` menyimpan state di `_lpgen_cognitive_state` pada item:

```text
new → research → outline → validated → writing → review
validated → outline   (conditional retry)
```

- `transition()` idempoten dan menolak edge ilegal.
- `checkpoint()` menyimpan outline yang sudah lolos di `_lpgen_checkpoint_outline`.
- `resume()` membaca checkpoint tanpa mengulang LLM yang sudah sukses.
- `route_after_validate()` memilih `write`, `retry_outline`, atau `stop` dengan batas attempt.
- Setting **Tinjau outline sebelum artikel** menandai `review_requested` di checkpoint.

Job queue WordPress tetap menjadi checkpoint runtime; tidak ada service Python/LangGraph yang dijalankan.

## 5. Memori topik ala mem0 + Sentence Transformers

Tabel baru `wp_lpg_topic_memory` (dbDelta, `LPG_DB_VERSION = 2`) menyimpan:

```text
campaign_id, item_id, keyword, intent, summary, outline, angle,
entities, url, embedding, created_at, checked_at
```

Sifatnya **ADD-only**: tidak pernah overwrite histori sudut pandang.

Pencarian `TopicMemory::search()` menggabungkan:

```text
0.55 × cosine(embedding query, embedding memor)
0.30 × lexical overlap
0.15 × entity overlap
```

Jika embedding tidak aktif/gagal:

```text
0.75 × lexical + 0.25 × entity
```

Embedding dipanggil lewat 9Router `/v1/embeddings` hanya saat Settings → AI → **Aktifkan embedding 9Router** dan model embedding diisi. Jika tidak, plugin memakai fallback leksikal tanpa biaya tambahan.

Skor `>= 0.72` ditandai `duplicate_risk`. `FunnelContent::assert_unique()` kini mengecek memori ini sebelum memindai korpus WordPress.

Temporal reasoning sederhana: record dengan `checked_at` lebih tua dari tahun WP minus satu mendapat penalti 0.8.

## 6. File baru/ubah

Baru:

- `includes/class-research-source.php`
- `includes/class-structured-output.php`
- `includes/class-orchestrator.php`
- `includes/class-topic-memory.php`
- `docs/12-riset-repo-pendukung.md`
- `docs/13-kognitif-v1.7.0.md`

Diubah antara lain:

- `includes/class-ai-client.php` — `embed()`
- `includes/class-prompts.php` — source/memory/state lines
- `includes/class-generator.php` — validate, orchestration, memory capture
- `includes/class-database.php` — tabel memory + `maybe_upgrade()`
- `includes/class-content-repository.php` — `research_sources`
- `includes/class-funnel-content.php` — semantic duplicate check
- `admin/class-campaign-ui.php` — input JSON sumber
- `admin/class-settings.php` — embedding & outline review
- `includes/class-activator.php` — fix `$index_key` undefined saat aktivasi fresh

## 7. Migrasi & rollback

- Tabel baru dibuat via `dbDelta`; tabel lama tidak diubah atau dihapus.
- `maybe_upgrade()` jalan di `admin_init` bila `lpgen_db_version` < 2.
- Kode lama tetap bisa membaca campaign tanpa `research_sources` (dianggap kosong).
- Rollback ke 1.6.0 aman untuk data: tabel memory hanya ditinggalkan, tidak dipakai.

## 8. Verifikasi

```text
php tests\run.php → 863 passed, 0 failed
php tests\smoke-bootstrap.php → PASS
php -l → No syntax errors
```

Regression baru mencakup:

- parser sumber & as_of,
- kutipan terverifikasi,
- schema + quote validation,
- routing retry,
- transisi state idempoten,
- ADD-only memory + risiko duplikat.

## 9. Batasan

- Tidak ada crawling web otomatis; sumber murni input user.
- Tidak ada Python, Qdrant, Neo4j, FAISS, LangSmith, atau layanan berbayar selain 9Router yang sudah ada.
- Embedding bergantung pada model yang tersedia di akun 9Router Anda; default tetap fallback leksikal.
- Human-in-the-loop saat ini berupa penandaan state/checkpoint, bukan penghentian antrean.
- Belum diuji dengan 9Router live atau WordPress produksi.
