# Kontrak desain 12 framework — LP Generator 1.5.2

## Pertanyaan yang dijawab

**“Gimana caranya supaya saat generate konten bisa menyesuaikan dengan framework desain yang sudah dibuat ini?”**

Jawabannya: desain tidak diserahkan ke AI. Desain dinyatakan sebagai **kontrak data** di server, lalu kontrak itu disuntikkan ke dua prompt AI, dipaksakan kembali saat rencana disimpan, divalidasi saat LP dibuat, dan finally dipakai renderer untuk menempatkan teks serta gambar. AI hanya menguasai **isi** (copy, sudut pandang, keyword gambar); plugin menguasai **struktur** (ID section, slot gambar, URL, attachment, markup).

Alurnya:

```text
DesignContract (12 preset, data statis)
  → prompt perencana framework: AI mengusulkan tujuan copy + image keyword
  → bind_plan(): server menimpa hint, layout, role, section_id, image_slot
  → fingerprint campaign ikut menyimpan design_id
  → prompt penulis LP: STRICT DESIGN CONTRACT dikirim lagi ke AI
  → LandingPage::validate(): design_id + urutan + section_id + image_slot diperiksa
  → ImagePipeline: upload/AI/Bing mengikuti slot & upload_key kontrak
  → renderer: AIDA memakai AidaLayout, 11 preset lain memakai renderer slot-aware
```

Jika AI mengirim `section_id` tertukar, `image_slot` asing, `design_id` campaign lain, atau URL gambar sendiri, hasil ditolak dan masuk mekanisme retry. Plugin tidak diam-diam memindahkan konten ke section lain.

## Daftar kontrak

Kode desain selalu `${slug}-1`. `ai` (Framework AI Khusus) sengaja tidak dikontrak dan tetap memakai jalur legacy.

| Framework | `design_id` | Section ID sesuai urutan | Slot gambar sesuai urutan |
|---|---|---|---|
| AIDA | `aida-1` | `aida-attention`, `aida-interest`, `aida-desire`, `aida-action` | `aida-hero-visual`, `aida-interest-visual`, `aida-desire-visual`, — |
| PAS | `pas-1` | `pas-problem`, `pas-agitate`, `pas-solution` | `pas-problem-visual`, `pas-agitate-visual`, `pas-solution-visual` |
| BAB | `bab-1` | `bab-before`, `bab-after`, `bab-bridge` | `bab-before-visual`, `bab-after-visual`, `bab-bridge-visual` |
| FAB | `fab-1` | `fab-features`, `fab-advantages`, `fab-benefits` | `fab-features-visual`, `fab-advantages-visual`, `fab-benefits-visual` |
| QUEST | `quest-1` | `quest-qualify`, `quest-understand`, `quest-educate`, `quest-stimulate`, `quest-transition`, `quest-act` | `quest-qualify-visual`, `quest-understand-visual`, `quest-educate-visual`, `quest-stimulate-visual`, `quest-transition-visual`, — |
| DAGMAR | `dagmar-1` | `dagmar-awareness`, `dagmar-comprehension`, `dagmar-conviction`, `dagmar-action` | `dagmar-awareness-visual`, `dagmar-comprehension-visual`, `dagmar-conviction-visual`, — |
| DIPADA | `dipada-1` | `dipada-desire-1`, `dipada-interest`, `dipada-persuasion`, `dipada-acceptance`, `dipada-desire-2`, `dipada-action` | `dipada-desire-1-visual`, `dipada-interest-visual`, `dipada-persuasion-visual`, `dipada-acceptance-visual`, `dipada-desire-2-visual`, — |
| IDEPA | `idepa-1` | `idepa-identification`, `idepa-demonstration`, `idepa-evaluation`, `idepa-prescription`, `idepa-acceptance` | `idepa-identification-visual`, `idepa-demonstration-visual`, `idepa-evaluation-visual`, `idepa-prescription-visual`, — |
| ACCA | `acca-1` | `acca-awareness`, `acca-comprehension`, `acca-conviction`, `acca-action` | `acca-awareness-visual`, `acca-comprehension-visual`, `acca-conviction-visual`, — |
| AISAS | `aisas-1` | `aisas-attention`, `aisas-interest`, `aisas-search`, `aisas-action`, `aisas-share` | `aisas-attention-visual`, `aisas-interest-visual`, `aisas-search-visual`, —, `aisas-share-visual` |
| 4P | `4p-1` | `4p-picture`, `4p-promise`, `4p-prove`, `4p-push` | `4p-picture-visual`, `4p-promise-visual`, `4p-prove-visual`, — |
| SPIN | `spin-1` | `spin-situation`, `spin-problem`, `spin-implication`, `spin-need-payoff` | `spin-situation-visual`, `spin-problem-visual`, `spin-implication-visual`, `spin-need-payoff-visual` |

Tanda — berarti section penutup/CTA tanpa gambar. Setiap slot juga punya `hint`, `layout`, `role`, `shape` dan `upload_key` di `includes/class-design-contract.php`.

## Yang dilakukan AI dan yang tidak

**AI boleh:**

- Menulis `heading`, `body`, `cards`/`bullets`, FAQ, judul SEO, meta description dan anchor deskriptif.
- Menentukan `image_keyword` per slot sebagai brief visual.
- Mengusulkan nama langkah saat framework disiapkan; nama/urutan tetap diverifikasi terhadap preset.

**AI tidak boleh:**

- Memilih `section_id`, `image_slot`, `hint`, `layout` atau `design_id` sembarangan — semuanya berasal dari kontrak.
- Mengirim `image_url`, `images`, attachment ID, HTML `<img>`, `<a>`, `<iframe>` atau `<script>`.
- Memindahkan isi hero ke section lain atau mengganti urutan langkah.

## Penempatan gambar

`DesignContract::placements()` menerjemahkan kontrak menjadi daftar slot untuk `ImagePipeline`. Sync otomatis memakai `image_keyword` dan `image_role` dari rencana campaign. Upload eksplisit dibaca dari kolom `upload_key` contract, misalnya `pas_solution_image_id` untuk `pas-solution-visual`. Engine tidak menebak dari urutan galeri umum.

Setiap gambar yang lolos menyimpan `slot_id`, `section_id`, `design_id`, `campaign_id` dan `campaign_fingerprint`. Renderer menolak slot ganda, slot asing, gambar campaign lain, desain lain atau sidik campaign yang sudah berubah. Gambar ditempatkan pada section yang sama dengan `slot_id`-nya; tidak ada fallback ke galeri atau hero.

## Renderer

- **AIDA** tetap memakai `includes/class-aida-layout.php` (hero Attention, Interest, kartu Desire, FAQ, Action).
- **11 preset lain** memakai `LandingPage::render()` generik. Section menerima `data-section-id`, `data-image-slot`, `data-step` dan `data-hint`; `layout` menentukan gaya (story, problem, process, benefits, comparison, proof, offer, hero, cta, dll.) dan `<ol>` dipakai untuk proses.

CSS dasar ada di `public/css/landing-page.css`, CSS khusus AIDA di `public/css/aida.css`, CSS tambahan pengguna dimuat setelah keduanya. Mode canvas dan mode mengikuti tema tetap tersedia.

## Yang harus dilakukan setelah update ke 1.5.2

Sidik framework campaign memasukkan `design_id` dan upload key. Rencana yang dibuat sebelum kontrak generik dianggap kedaluwarsa.

1. Buka campaign, lalu jalankan ulang **Siapkan framework campaign**.
2. Perbarui pilihan upload per slot bila memakai Media Library.
3. Generate ulang LP. Item lama tidak diubah otomatis.

Tanpa langkah itu, AI/validator masih melihat rencana lama dan LP baru dapat ditahan dengan pesan kontrak.

## Batasan

- Ini adalah **kontrak struktural + renderer generik yang sadar slot**, bukan 12 mockup visual buatan tangan. AIDA satu-satunya layout khusus.
- Relevansi isi foto/AI/Bing, hak pakai dan kualitas copy tetap perlu ditinjau manusia. ID slot tidak membuktikan objek di dalam gambar benar.
- `Framework AI Khusus` (`ai`) belum terikat kontrak ini.
- Impor banyak keyword masih ditunda sesuai permintaan; rilis ini hanya menyelesaikan LP HTML untuk 12 preset.

## Verifikasi lokal

- `php tests\run.php` → **815 passed, 0 failed** (termasuk `test-zz-design-contract.php` untuk 12 kontrak, prompt, validasi dan penempatan gambar PAS).
- `php tests\smoke-bootstrap.php` lolos; `php -l` untuk file PHP yang diubah lolos.
- `node tests\test-admin-runner.js` → PASS tombol Mulai Generate mengirim payload `lpgen` tanpa exception.
- Tidak ada WordPress live, kredensial 9Router atau domain target yang diuji pada sesi ini. Generate/publish/indexing produksi belum diverifikasi.
