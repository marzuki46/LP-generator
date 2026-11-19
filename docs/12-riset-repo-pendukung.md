# 12 — Riset Repo Pendukung (Pola yang Dapat Diadaptasi)

Tujuan: mengambil **pola konseptual** dari 5 repositori sumber terbuka untuk diimplementasikan ulang dalam PHP di LP Generator. Bukan menambah dependensi Python. Sumber yang dibaca: README resmi, `docs/`, dan kode sumber di repo masing-masing. Catatan: file `knowledge_storm/storm_wiki/modules/article_gen.py` tidak dapat dibaca (404), sehingga mekanisme sitasi di bawah merujuk ke `interface.py` dan README saja.

---

## 1. stanford-oval/storm — riset dua tahap + klaim bersumber

- STORM memecah artikel panjang bersitasi menjadi dua tahap: **pre-writing** (riset internet + outline) lalu **writing** (artikel + sitasi). (https://raw.githubusercontent.com/stanford-oval/storm/main/README.md)
- Kedalaman riset datang dari **Perspective-Guided Question Asking** dan **Simulated Conversation** antara penulis dan pakar; bukan sekadar prompt langsung. (https://raw.githubusercontent.com/stanford-oval/storm/main/README.md)
- Pipeline dipisah menjadi 4 modul — KnowledgeCuration, OutlineGeneration, ArticleGeneration, ArticlePolishing — dengan kontrak abstrak di `interface.py` dan flag `do_research`, `do_generate_outline`, `do_generate_article`, `do_polish_article`. (https://raw.githubusercontent.com/stanford-oval/storm/main/knowledge_storm/interface.py)
- Struktur `Information` menyimpan `url` sebagai UUID, `title`, `description`, `snippets`, `meta` (berisi `question`/`query`), dan `citation_uuid` — ini padanan langsung dari structured record sumber + klaim. (https://raw.githubusercontent.com/stanford-oval/storm/main/knowledge_storm/interface.py)
- `Retriever.retrieve()` menerima `exclude_urls` dan membersihkan sitasi pada snippet (`remove_citations`) agar tidak terjadi multi-hop citation. (https://raw.githubusercontent.com/stanford-oval/storm/main/knowledge_storm/interface.py)
- `VectorRM` memungkinkan grounding pada **korpus milik sendiri** lewat CSV berkolom `content`, `title`, `url`, `description` — cocok dengan kebutuhan "sumber diinput user, tanpa crawling". (https://raw.githubusercontent.com/stanford-oval/storm/main/examples/storm_examples/README.md)

### Adaptasi ke LP Generator
- Model `Lpg_SourceRecord` (url, title, publisher, published_at, `checked_at`, claims[]) dan `Lpg_Claim` (type, `as_of`, `exact_quote`, `source_url`, `citation_index`) — cermin `Information` + `citation_uuid`.
- `lpg_build_source_table()` mengisi tabel klaim hanya dari input user + post WP yang sudah publish; tidak ada fetch web otomatis.
- `lpg_attach_citations()` menyisipkan `[n]` ke draft dan menyimpan daftar sumber; `lpg_strip_citations()` membersihkan snippet sebelum diprompts.
- Time sync (a): `lpg_time_anchor()` membaca timezone WP (`wp_timezone()`/opsi `timezone_string`), lalu `lpg_claim_is_stale()` memflag klaim yang `as_of`-nya lebih tua dari anchor 2026.
- Tambahkan node "research" eksplisit sebelum outline di `CognitiveEngine`, menghasilkan tabel klaim yang dipakai dua tahap outline.

---

## 2. mem0ai/mem0 — memori jangka panjang & retrieval multi-sinyal

- Arsitektur mem0 mengekstrak, mengonsolidasi, dan mengambil informasi penting secara dinamis lintas sesi, dengan varian graph memory untuk relasi kompleks. (https://arxiv.org/abs/2504.19413)
- Algoritma baru (Apr 2026): **single-pass ADD-only extraction** — satu LLM call, tanpa UPDATE/DELETE; memori menumpuk dan tidak menimpa yang lama. (https://raw.githubusercontent.com/mem0ai/mem0/main/README.md)
- **Entity linking**: entitas diekstrak, di-embed, dan ditautkan lintas memori untuk boosting retrieval. (https://raw.githubusercontent.com/mem0ai/mem0/main/README.md)
- **Multi-signal retrieval**: semantic, BM25 keyword, dan entity matching diskor paralel lalu digabung (fused). (https://raw.githubusercontent.com/mem0ai/mem0/main/README.md)
- **Temporal reasoning**: retrieval sadar waktu yang memilih instance ber-tanggal tepat untuk pertanyaan tentang keadaan kini, masa lalu, atau rencana. (https://raw.githubusercontent.com/mem0ai/mem0/main/README.md)
- Paper melaporkan graph memory ~2% lebih tinggi dari base, p95 latency 91% lebih rendah, dan penghematan token >90% dibanding full-context. (https://arxiv.org/abs/2504.19413)

### Adaptasi ke LP Generator
- Tabel `wp_lpg_topic_memory` menyimpan keyword, intent, summary, outline, angle, entities (JSON), URL, embedding, `created_at`/`checked_at` — lintas campaign.
- `lpg_memory_add()` bersifat **ADD-only**: selalu append baris baru, jangan overwrite, agar histori angle/outline terdokumentasi.
- `lpg_memory_search($query)` = hybrid: cosine semantic via 9Router `/v1/embeddings` + skor leksikal (token/FULLTEXT) + overlap entitas; skor digabung dengan bobot.
- `lpg_score_with_time()` menurunkan peringkat record yang `checked_at`-nya lebih tua dari time anchor 2026 (temporal reasoning versi sederhana).
- `lpg_entity_overlap()` memakai field `entities` sebagai sinyal ketiga di samping semantic dan lexical.

---

## 3. huggingface/sentence-transformers — embeddings, similarity, retrieve & rerank

- Model mengubah teks jadi vektor; `model.similarity()` menghitung matriks skor kemiripan (contoh cosine 0.6660 untuk kalimat paraphrase). (https://raw.githubusercontent.com/UKPLab/sentence-transformers/master/README.md)
- Semantic search: embed korpus dan query ke ruang vektor yang sama, lalu ambil embedding terdekat; manual implementation disarankan untuk korpus sampai ~1 juta entri. (https://raw.githubusercontent.com/UKPLab/sentence-transformers/main/examples/sentence_transformer/applications/semantic-search/README.md)
- Pembedaan **symmetric vs asymmetric search** penting — query pendek vs dokumen panjang memakai `encode_query`/`encode_document`, bukan `encode` generik. (https://raw.githubusercontent.com/UKPLab/sentence-transformers/main/examples/sentence_transformer/applications/semantic-search/README.md)
- Pola **Retrieve & Re-Rank** dua tahap: bi-encoder mengambil kandidat, cross-encoder menskor ulang untuk hasil final. (https://raw.githubusercontent.com/UKPLab/sentence-transformers/main/examples/sentence_transformer/applications/semantic-search/README.md)
- Paraphrase mining dan duplicate detection tersedia sebagai aplikasi siap pakai — dasar pengecekan kemiripan topik. (https://raw.githubusercontent.com/UKPLab/sentence-transformers/master/README.md)
- Optimisasi: normalisasi vektor lalu dot-product setara cosine; ANN (FAISS/HNSW) hanya berguna untuk jutaan vektor. (https://raw.githubusercontent.com/UKPLab/sentence-transformers/main/examples/sentence_transformer/applications/semantic-search/README.md)

### Adaptasi ke LP Generator
- `lpg_embed($text)` memanggil 9Router `/v1/embeddings`; `lpg_cosine($a, $b)` di PHP (normalisasi + dot product).
- `lpg_lexical_score()` sebagai **fallback** bila embedding gagal/kuota habis: token overlap + bobot judul/keyword (analog BM25).
- `lpg_memory_search()` menerapkan dua tahap: kandidat leksikal cepat (MySQL LIKE/FULLTEXT) → rerank dengan cosine embedding.
- FunnelContent memakai `lpg_similarity($a, $b)` dengan threshold untuk duplicate check (pola paraphrase mining).
- Simpan embedding sebagai array JSON ringkas di tabel memori; korpus kecil → tidak perlu ANN/FAISS.

---

## 4. langchain-ai/langgraph — orkestrasi stateful & checkpoint

- LangGraph memodelkan workflow sebagai **State + Nodes + Edges**; node melakukan kerja, edge menentukan langkah berikutnya, dan langkah deterministik bisa dicampur dengan langkah LLM. (https://docs.langchain.com/oss/python/langgraph/graph-api.md)
- **Conditional edges** dan `Command` mengarahkan berdasarkan state; `Send` melakukan fan-out map-reduce saat jumlah item baru diketahui saat runtime. (https://docs.langchain.com/oss/python/langgraph/graph-api.md)
- **Persistence/checkpoint** disimpan di boundary super-step, sehingga run bisa dilanjutkan setelah failure atau interrupt. (https://docs.langchain.com/oss/python/langgraph/overview)
- **Human-in-the-loop**: eksekusi bisa dijeda, state diperiksa/diubah, lalu dilanjutkan. (https://raw.githubusercontent.com/langchain-ai/langgraph/main/README.md)
- Memory dibedakan menjadi short-term thread state dan long-term memory lintas sesi. (https://docs.langchain.com/oss/python/langgraph/overview)
- Karena node dijalankan ulang dari awal saat resume, docs menyarankan desain **idempoten** (upsert / read-before-write). (https://docs.langchain.com/oss/python/langgraph/graph-api.md)

### Adaptasi ke LP Generator
- `Lpg_Orchestrator`: state array (`topic`, `framework`, `sources`, `claims`, `outline`, `draft`, `validation`, `attempts`) dengan node `lpg_node_research`, `lpg_node_outline`, `lpg_node_validate`, `lpg_node_generate`.
- Routing kondisional `lpg_route_after_validate()` → `retry_outline` | `generate` (cermin conditional edges), dengan batas attempt.
- Checkpoint per stage disimpan di job queue `Generator` (tabel job WP); resume tanpa mengulang LLM call yang sudah sukses; node dibuat idempoten via upsert.
- Human-in-the-loop: pause sebelum generate agar outline bisa diedit di admin (interrupt sederhana lewat status job `awaiting_review`).
- Pisahkan memory: short-term = state job; long-term = `wp_lpg_topic_memory`.

---

## 5. 567-labs/instructor — structured output + retry validasi

- `response_model=` (Pydantic) menghasilkan data tervalidasi dan typed langsung, tanpa parsing JSON manual. (https://raw.githubusercontent.com/567-labs/instructor/main/README.md)
- Validasi yang gagal otomatis di-retry dengan pesan error; diatur lewat `max_retries`. (https://raw.githubusercontent.com/567-labs/instructor/main/README.md)
- Contoh `field_validator` sederhana: `validate_age` menolak nilai negatif, lalu Instructor mencoba ulang. (https://raw.githubusercontent.com/567-labs/instructor/main/README.md)
- Validasi berbasis context: model `Citation` memakai `ValidationInfo.context` dan `verify_quote_exists` untuk memastikan `quote` benar-benar ada di `source_text`. (https://python.useinstructor.com/concepts/retrying/)
- Kontrol retry: `token_budget`, `InstructorRetryException`, `failed_attempts`, `n_attempts`, exponential backoff; docs menekankan **selalu pasang stop condition**. (https://python.useinstructor.com/concepts/retrying/)
- Nested objects dan partial streaming didukung — cocok untuk schema bersarang. (https://raw.githubusercontent.com/567-labs/instructor/main/README.md)

### Adaptasi ke LP Generator
- `Lpg_StructuredOutput` dengan `lpg_validate_outline($payload, $schema)`: `json_decode` + cek field wajib/tipe + aturan; kembalikan `WP_Error` berisi field yang gagal.
- `lpg_generate_validated($prompt, $schema, $max_retries = 3)`: kirim ke 9Router → validasi → bila gagal, kirim ulang menyertakan pesan error (reask) dengan backoff terbatas.
- `lpg_verify_quote_exists($claim, $sources)` menolak `exact_quote` yang tidak ada di sumber — cermin langsung `Citation.verify_quote_exists`.
- Validasi rekursif mengikuti DesignContract (sections → subsections); simpan `n_attempts` dan `failed_attempts` ke log job untuk observabilitas.
- Selalu batasi: `max_retries`, `token_budget` (estimasi token), dan `usleep` eksponensial — jangan retry tanpa henti.

---

## Ringkasan rekomendasi

### Urutan increment minimum (dari yang paling dasar)
1. **Fondasi waktu & data (a)** — `lpg_time_anchor()` dari WP settings + `Lpg_SourceRecord`/`Lpg_Claim` dengan `checked_at`, `as_of`, `exact_quote`. Tanpa ini, fitur lain tidak punya dasar.
2. **Structured output + retry (c, bagian validasi)** — `lpg_validate_outline()` + `lpg_generate_validated()` dengan retry terbatas dan `lpg_verify_quote_exists()`.
3. **Orkestrasi state machine (c, bagian alur)** — `Lpg_Orchestrator` + checkpoint di job queue + routing kondisional retry/generate.
4. **Topic memory (b)** — tabel `wp_lpg_topic_memory`, `lpg_memory_add()` ADD-only, `lpg_memory_search()` hybrid semantic + lexical fallback.
5. **Rerank & dedup** — cosine + threshold kemiripan, integrasi ke `FunnelContent`.

### Yang TIDAK diadopsi
- Menjalankan Python atau `pip install` knowledge-storm / mem0ai / sentence-transformers / langgraph / instructor di WordPress. **Pola saja, bukan dependensi.**
- Crawling web otomatis (search engine bawaan STORM). Sumber tetap input user; analog `VectorRM` adalah structured record, bukan pengambilan URL otomatis.
- Layanan berbayar/eksternal di luar 9Router: Qdrant Cloud, Bing/You.com/Tavily, LangSmith, Mem0 Platform, Hugging Face Inference, atau OpenAI API langsung.
- Graph memory/Neo4j, FAISS/HNSW, fine-tuning model, dan cross-encoder reranker lokal — overkill untuk korpus kecil; cosine PHP + FULLTEXT cukup.
- Retry tak terbatas atau agen otonom penuh; selalu ada batas (`max_retries`, `token_budget`, `recursion_limit` sederhana).
- Menyalin prompt/model STORM apa adanya; yang diambil hanya struktur dua tahap + mekanisme sitasi.
