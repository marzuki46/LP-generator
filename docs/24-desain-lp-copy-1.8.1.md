# v1.8.1 — Desain LP lebih menjual, copy lebih dalam, update manifest

## 1. Versi update masih 1.7.5

WordPress membaca `version.json` dari `raw.githubusercontent.com`. Edge cache raw masih menyajikan 1.7.5 walaupun file di branch `main` sudah 1.8.0 (terbukti via GitHub API dan jsDelivr).

Perbaikan:

- `Updater::MANIFEST_URL` sekarang: `https://cdn.jsdelivr.net/gh/marzuki46/LP-generator@main/version.json`
- Fallback tetap `raw.githubusercontent.com` bila jsDelivr gagal.
- Cache-busting `?t=time()` dan header no-cache tetap dipakai.

Karena plugin yang terpasang masih 1.7.5, paket 1.8.1 perlu diupload manual sekali. Setelah itu update otomatis memakai manifest baru.

## 2. Related article jadi card

Sebelumnya related link berupa daftar polos dengan judul “Pelajari sebelum menentukan pilihan”. Sekarang:

- Judul: **Panduan terkait**, subjudul: “Pelajari sebelum menentukan pilihan”.
- Kartu responsif berisi:
  - kicker stage (`TOFU`, `MOFU`, `TAG`),
  - judul artikel,
  - tautan “Baca panduan →”.
- Slot related kini berlaku untuk **semua model LP**, bukan hanya AIDA.
- Artikel tag ikut masuk daftar related.

## 3. Harga dan tombol pesan

LP kini punya strip komersial di bagian atas:

```text
MULAI DARI
Mulai Rp 800.000                    [ Pesan Sekarang ]
```

- Harga diambil dari `campaign.price`. Jika kosong: “Harga disesuaikan kebutuhan”.
- Tombol memakai `offer_url`, labeldefault `Pesan Sekarang` / `Order Now`.
- Untuk LP afiliasi, tombol di strip tidak dipakai agar disclosure tetap muncul sebelum CTA pertama di hero.

## 4. Copy lebih informatif

Prompt LP diminta:

- setiap section **2–3 paragraf pendek** (sekitar 120–220 kata),
- alur per paragraf: konteks → contoh konkret → hasil/keberatan,
- minimal klaim rekaan,
- FAQ 4–6.

Artikel pendukung juga diminta 2–3 paragraf per heading, bukan satu paragraf.

## 5. Tidak ada label AIDA di konten

Prompt melarang heading seperti `Attention:` atau `Desire:`. Sebagai pengaman, `FunnelContent::strip_framework_labels()` menghapus prefix label framework dari title, intro, heading, body, dan CTA sebelum konten disimpan.

## 6. Tabel pipe → HTML

AI sering mengirim tabel seperti:

```text
| Faktor | Media Sosial | Website |
| --- | --- | --- |
| Jangkauan | Algoritma | SEO |
```

`FunnelContent::extract_table()` sekarang:

- memisahkan baris pipe dari paragraf,
- menghasilkan `columns` + `rows` terstruktur,
- membuang baris separator,
- menyeragamkan jumlah sel per baris.

`Publisher::build_blocks()` merendernya sebagai blok `wp:table` WordPress, bukan teks pipe.

## 7. Responsivitas

Playwright 390×844:

- tidak ada overflow horizontal (`scrollWidth === clientWidth`),
- nav membungkus,
- offerbar price + order tetap terbaca,
- related cards menjadi satu kolom,
- sticky CTA tidak menutupi konten penting.

## 8. Verifikasi

```text
php tests\run.php → 911 passed, 0 failed
php -l → No syntax errors
Playwright desktop 1440px + mobile 390px → PASS
```

## 9. Catatan

- Kadang perlu **regenerate LP** agar copy 2–3 paragraf dan related card muncul di item lama.
- Update otomatis baru akurat setelah 1.8.1 terpasang manual.
