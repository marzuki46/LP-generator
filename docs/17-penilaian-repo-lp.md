# Penilaian 6 repo generator LP

Catatan: ini penilaian arsitektur berdasarkan README/source resmi yang bisa diakses. Belum ada yang dipasang atau diuji di server Anda. Pola yang diambil tetap **diimplementasikan ulang di PHP**, bukan dijadikan dependency Python/Node di plugin.

## Ringkasan

| Repo | Status sumber | Penilaian | Rekomendasi |
|---|---|---|---|
| OpenLen | Tidak ditemukan repo OSS yang jelas; hasil pencarian mengarah ke produk komersial openlen.com | Informasi belum cukup | Kirim URL pasti dulu |
| AICom Landing | `alexar76/aicom-landing`, MIT, Node 18+ | Bagus untuk pola dua agen + preset gaya | Referensi menengah |
| 302 AI Web Page Generator | `302ai/302_web_page_generator`, React/TS | Kuat untuk screenshot→HTML & edit komponen, tapi berat | Referensi rendah |
| OpenPage | `buildingopen/openpage`, MIT, React/TS | **Paling cocok** dengan JSON-first DesignContract kami | Referensi tinggi |
| PressGo Builder | `acehobojoe/pressgo-builder`, GPLv2, WP + Elementor | Relevan untuk WP, tapi terikat Elementor | Referensi menengah |
| Automattic Minimalistic Site Builder | `Automattic/minimalistic-site-builder` | **Paling matang untuk WordPress native**, cakupan lebih luas dari LP | Referensi tinggi |

## 1. OpenLen

Saya belum menemukan repo GitHub resmi yang sesuai deskripsi Anda. Hasil yang ada mengarah ke layanan `openlen.com` yang disebut sebagai agent yang merender halaman di browser dan mengukur hasilnya sendiri.

**Kesimpulan:** belum bisa dinilai. Kirim URL repo/blog eksaknya bila ingin saya baca.

## 2. AICom Landing

Sumber: https://github.com/alexar76/aicom-landing

Fakta penting:

- MIT, Node 18+, satu prompt → satu `index.html` mandiri dalam 30–60 detik.
- Dua agen: **Architect** (JSON: layout, copy language, visual plan, sections) lalu **Developer** (HTML + CSS inline + SVG).
- **20 preset gaya** di `styles/presets.json` (glass, brutalist, editorial, organic, cyberpunk, dll.).
- Preview pakai iframe `sandbox` tanpa `allow-same-origin`; CSP; rate limit; JSON retry default 3.
- Tidak ada database/admin panel/QA pipeline.

**Cocok untuk kita:** pola Architect→Developer sudah mirip `generate_topic → generate_article` + `DesignContract`. Preset gaya sebagai data juga selaras dengan `VisualIdentity`.

**Tidak diadopsi:** Node runtime, preview server, badge injection, agent widget.

## 3. 302 AI Web Page Generator

Sumber: https://github.com/302ai/302_web_page_generator

Fakta penting:

- React + Tailwind + Shadcn UI; butuh API key 302.AI.
- Natural language **atau screenshot** → HTML+Tailwind, React, Bootstrap, Vue, Ionic, SVG.
- DALL-E 3 untuk placeholder image; import/export HTML; edit level komponen.
- Preview desktop/mobile real-time.

**Cocok untuk kita:** ide screenshot→desain dan edit komponen bisa menginspirasi preview LP di masa depan.

**Kenapa prioritas rendah:** stack Node/React, biaya API di luar 9Router, dan plugin kita sudah punya renderer HTML + kontrak desain yang lebih deterministik daripada generate kode bebas.

## 4. OpenPage

Sumber: https://github.com/buildingopen/openpage — baca README dari branch `master` (`main` 404).

Fakta penting:

- **JSON sebagai single source of truth**, MIT, React 19/TS.
- 19 tipe block, 42 layout variant, 10 theme preset.
- Editor visual + `POST /api/generate` (Gemini) menghasilkan `SiteConfig` JSON.
- Export HTML mandiri: style inline, Tailwind CDN, Google Fonts.
- Alur: `Visual Editor ⇄ JSON Config ⇄ Agent API → Renderer → HTML`.

**Cocok untuk kita:** ini paling dekat dengan arah `DesignContract`. Perbedaannya: mereka menyimpan theme + blocks sebagai JSON penuh; kita menyimpan kontrak per framework + AI copy + renderer PHP.

**Yang bisa dipelajari:**

- typed JSON schema untuk section/variant/theme;
- katalog `block + variant + props` yang bisa dipetakan ke section LP kita;
- theme tokens (warna, font, radius) alih-alih hardcode CSS.

**Tidak diadopsi:** React editor, Tailwind CDN, endpoint Gemini.

## 5. PressGo Builder

Sumber: https://github.com/acehobojoe/pressgo-builder

Fakta penting:

- Plugin WordPress GPLv2; butuh **Elementor Free** + Claude API key BYOK.
- Prompt/screenshot → config JSON → PHP generator → **native Elementor widgets**.
- 19 tipe section, 48 layout variant; streaming SSE; `config-validator` + default.
- Tanpa shortcode; hasil diedit di Elementor.

**Cocok untuk kita:** validator config + katalog section/variant + alur streaming status.

**Resiko:** ketergantungan Elementor bertentangan dengan desain kita saat ini yang menghasilkan native WordPress HTML block dan bekerja tanpa page builder. Tidak disarankan dijadikan dependency.

## 6. Automattic Minimalistic Site Builder

Sumber: https://github.com/Automattic/minimalistic-site-builder

Fakta penting:

- Satu prompt → situs WordPress multi-halaman: block theme (`theme.json`, templates, parts) + plugin content seeder.
- Pipeline berlangkah dengan model tier besar/kecil, `siteSpec` canonical JSON Schema, `warnings.json`, log per step.
- **PHP block fixer** in-process tanpa Node setelah perubahan terbaru.
- Preview via Playground/Studio; image generation opsional via Gemini proxy.
- Host bisa mengirim `siteSpec` sendiri agar step site-spec tidak memanggil LLM.

**Cocok untuk kita:** pendekatan WP-native, schema kanonik, validator/repair deterministik, dan pipeline dengan checkpoint sangat relevan dengan `Orchestrator` + `StructuredOutput` kita.

**Kenapa bukan copy langsung:** cakupannya membuat seluruh situs/theme, sedangkan kita hanya membuat satu LP per campaign di dalam plugin yang sudah berjalan.

## Rekomendasi urutan riset lanjutan

1. **OpenPage** — ambil pola block/variant/theme JSON untuk memperluas `DesignContract` tanpa mengubah renderer secara radikal.
2. **Automattic minimalistic-site-builder** — ambil pola canonical JSON schema + PHP validator/repair + warnings per step.
3. **AICom Landing** — ambil pola style preset data-driven dan retry JSON yang sudah kita miliki versi sederhananya.
4. **PressGo** — hanya untuksection/variant catalog; jangan bawa Elementor.
5. **302 AI** — tunda; nilai tambahnya di screenshot-to-design, bukan alur teks→LP kita.
6. **OpenLen** — kirim URL dulu.

## Keputusan yang disarankan

Jangan menginstal repo mana pun ke WordPress. Ambil **pola**, tetapkan kontrak data di PHP, dan uji lewat test suite yang sudah ada. Sebelum saya mengubah kode, pilih satu referensi yang ingin didalami lebih lanjut.
