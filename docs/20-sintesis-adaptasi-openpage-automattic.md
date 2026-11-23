# Sintesis Pendalaman: OpenPage + Automattic → LP Generator

Riset sumber primer:

- `docs/18-riset-openpage.md` — OpenPage, branch `master`
- `docs/19-riset-automattic.md` — Automattic minimalistic-site-builder, branch `trunk`

Dokumen ini **rencana**, belum implementasi. Tidak ada kode plugin yang diubah.

## 1. Kesimpulan singkat

Kedua repo bertemu di satu prinsip yang sama dengan `DesignContract` kita:

> **Struktur adalah data kanonik. AI boleh mengisi nilai, bukan menentukan bentuk.**

| Aspek | OpenPage | Automattic | LP Generator sekarang |
|---|---|---|---|
| Sumber kebenaran | `SiteConfig` JSON (blocks + theme) | `siteSpec.json` kanonik | `DesignContract` per framework + AI copy |
| Validasi | hand-rolled di klien, fallback template | normalisasi deterministik + warning, bukan gate | `StructuredOutput` + retry |
| Struktur hook | block `type`/`variant`/`props` | 17 field tetap + pohon `pages` | `section_id`, `image_slot`, `hint`, `layout`, `role`, `shape`, `upload_key` |
| Theme | 20 token + 10 preset override | `theme.json` + design direction + palette reconcile | `VisualIdentity` (palet tunggal navy/orange/gold) |
| Renderer | registry 19 komponen React | block markup WP + `PhpBlockFixer` | `LandingPage` + `AidaLayout`, native WP HTML |
| Identitas HTML | **tidak ada** `data-*` pada blok | blok WP standar | **sudah ada** `data-section-id`, `data-image-slot`, `data-step`, `data-hint` |
| Checkpoint | tidak ada | `meta.json`, `--from/--until`, artifact per step | `Orchestrator` state + checkpoint outline |
| Warning | tidak ada | `warnings.json` per step | Activity Log levels |
| Gambar | Pexels opsional | `images.json` status + placeholder + regenerate | WebP + slot provenance, tanpa status/placeholder |
| Model | Gemini flash, tanpa retry di server | tier large/small per step | 9Router models list (belum per step) |

Kita **sudah lebih baik** dari OpenPage di identitas HTML dan validasi server. Kita **tertinggal** di tiga hal: theme/variant catalog, canonical spec + normalizer, dan step graph + warning artifact.

## 2. Apa yang tidak boleh diadopsi

- React/TypeScript/Vite dari OpenPage; Tailwind Play CDN di hasil ekspor (butuh internet, jadi bukan “self-contained”).
- Node runtime, Pexels, Vercel deploy, GA/PostHog dari OpenPage.
- Gemini langsung, Anthropic/OpenAI/OpenRouter langsung, WPCOM AI proxy dari Automattic—kita sudah punya 9Router.
- Playground/Studio, screenshot CI, dan full-site/theme generation Automattic—di luar scope plugin ini.
- Menyalin prompt atau preset warna mereka; `VisualIdentity` tetap satu-satunya identitas visual.

## 3. Gap analysis terhadap plugin kita

| Gap | Dampak saat ini | Prioritas |
|---|---|---|
| `DesignContract` belum punya theme token + katalog variant/props | Sulit menambah variasi desain tanpa mengubah renderer; palet baru rawan menyimpang | Tinggi |
| Belum ada canonical `lpSpec.json` + normalizer deterministik | AI/output host bisa masuk dengan bentuk sedikit beda → retry atau gagal | Tinggi |
| Belum ada `warnings.json` per job | Perbaikan deterministik tidak tercatat rapi; sulit dibedakan dari error | Tinggi |
| `Orchestrator` belum punya deklarasi `reads`/`writes` dan resume berbasis artifact | Resume mengandalkan state meta, belum graph yang tervalidasi | Sedang |
| Belum ada tier model per step | Semua step memakai model fallback list yang sama | Sedang |
| Image pipeline belum punya status `pending/placeholder/completed/failed` | Gambar gagal tidak bisa di-regenerate selektif tanpa request ulang semua | Sedang |
| Belum ada exporter HTML 12-section tanpa CDN | Preview only di WP; tidak bisa dibagikan sebagai file statis | Rendah |
| Belum ada screenshot regression | Perubahan CSS tidak punya bukti visual otomatis | Rendah |

## 4. Roadmap adaptasi yang disarankan

### Increment A — Canonical LP Spec + Normalizer + Warnings

Adaptasi dari Automattic `siteSpec` + OpenPage `validateSiteConfig`.

Buat:

- `schemas/lp-spec.schema.json` (JSON Schema 2020-12), field tetap snake_case, `additionalProperties: true` untuk fakta host.
- `LpSpecNormalizer`: `normalize()` idempoten, repair yang direview + warning, bukan gate yang menghentikan.
- `LpWarnings`: artifact `{ stepId: [messages] }` dengan dedup, `addWarnings()`, `replaceWarnings()`, format `authored=…; delivered=…; disposition=…`.

Acceptance criteria:

- `normalize(normalize(x)) === normalize(x)`.
- Input malformed (nama kosong, slug salah, sections bukan array) menghasilkan output kanonik + warning, bukan exception.
- Dua perbaikan identik tidak menduplikasi baris warning.
- Saat spec disuplai host/campaign, tidak ada panggilan 9Router untuk tahap spec.

### Increment B — Theme tokens + variant catalog

Adaptasi dari OpenPage `ThemeConfig`, `blockMetadata`, `resolveTheme`, `themeToCSS`.

Perluas `DesignContract`:

- `theme_tokens` default (background, surface, text, accent, gold, border, font, radius).
- `variants[]` per section dengan `default_props`.
- `resolveTheme(partial)` = `{...defaultTheme, ...partial}`; `VisualIdentity` menjadi preset `default`.
- Inject CSS custom properties via `wp_add_inline_style`, bukan hardcode di tiap section.

Acceptance criteria:

- Tiap kontrak punya `variants` tidak kosong dan `default_props` dipakai renderer.
- Token `--lp-accent` sama dengan `VisualIdentity::ACCENT`.
- Variant asing fallback ke `variants[0]` dan tercatat sebagai warning, bukan menggagal job.

### Increment C — Step graph + resume + model tier

Adaptasi dari Automattic `StepGraph`, `Pipeline`, `config/models.json`.

Perluas `Orchestrator`:

- Tiap node punya `reads`/`writes`; graph divalidasi saat assembly.
- Resume dari step tertentu membaca artifact yang sudah ada.
- `step_tiers` large/small + override `LPG_MODEL_<STEP>`.
- Manifest ditulis staged-atomic.

Acceptance criteria:

- Resume dari tengah menghasilkan artifact byte-identik dengan full run.
- ID duplikat atau `read` sebelum `write` ditolak.
- Step deterministik membuktikan nol request 9Router.

### Increment D — Image status & placeholder policy

Adaptasi dari Automattic `InitialImagePolicy`, `images.json`, `ImagePlaceholder`.

Perluas `ImagePipeline`:

- Status `pending|placeholder|completed|failed` per slot.
- Slot hero/utama eligible generate; slot interior bisa ditunda sebagai placeholder lokal dengan format/aspect sama.
- Rerun tidak mengulang request yang sudah `completed`.
- Mode regenerate selektif.

Acceptance criteria:

- Tepat slot mana yang menghasilkan request (bukan menebak).
- Placeholder punya MIME/aspect sesuai WebP 16:9.
- Rerun tidak menambah request untuk slot completed.

### Increment E — Opsional: export & visual regression

- Exporter HTML statis untuk 12 section, CSS inline, tanpa CDN—hindari bug OpenPage yang membuang 6 tipe jadi komentar.
- Screenshot full-page dengan scroll dulu, lalu simpan sebagai bukti regresi.

## 5. Urutan eksekusi yang disarankan

```text
A (spec + warning) → B (theme + variant) → C (graph + tier) → D (image policy) → E (opsional)
```

A dan B memberi dampak langsung ke stabilitas generate LP. C dan D soal operasional/biaya. E baru setelah desain stabil.

## 6. Keputusan yang dibutuhkan

Sebelum saya mengubah kode, pilih salah satu:

1. **Mulai dari Increment A + B** — paling langsung memperkuat kontrak desain dan stabilitas LP.
2. **Increment A saja** — paling aman, kecil, bisa diuji sendiri.
3. **Semua A–D sekaligus** — perubahan besar, butuh waktu verifikasi lebih panjang.

Catatan: OpenLen masih menunggu URL repo eksak dari Anda.
