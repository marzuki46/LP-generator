# v1.7.3 — WIB, contoh JSON, dan log lebih bersih

## 1. Waktu WIB, bukan UTC

Activity Log dan jadwal konten sekarang memakai timezone WordPress.

- `Utils::timezone_label()` mengembalikan `WIB` untuk `Asia/Jakarta`, `WITA` untuk `Asia/Makassar`, `WIT` untuk `Asia/Jayapura`.
- `Utils::display_time()` mengubah `created_at` UTC dari database menjadi waktu lokal via `get_date_from_gmt()`.
- Header tabel log berubah dari **Waktu (UTC)** menjadi **Waktu (WIB)**.
- `queue_snapshot()` menyertakan `next_run_local` dan `timezone_label`; `queue_details()` menyertakan `run_at_local`.
- Runner dashboard menampilkan: `Jeda berikutnya: MM:SS · 2026-11-19 16:19:21 WIB`.

Data di database tetap UTC—ini benar untuk penyimpanan. Yang berubah hanya tampilan.

## 2. Error AIDA: “hanya memakai teks, bullets dan kartu”

Model mengirim `table`/`quote` pada AIDA, padahal kontrak AIDA melarangnya. Dua perbaikan:

### Server membersihkan, bukan menggagalkan

`DesignContract::bind_content()` sekarang:

- menghapus `table` dan `quote` dari semua section AIDA;
- mengosongkan `cards`/`bullets` pada hero (section 1) dan Action (section 4);
- menyamakan `heading`/`body` hero dengan `title`/`intro` root.

Jadi job tidak lagi gagal hanya karena model menambahkan blok yang tidak didukung.

### Prompt diberi contoh JSON nyata per framework

`Prompts::lp_example()` membuat contoh copy-only JSON sesuai kontrak framework:

- AIDA → 4 section, `table`/`quote` = `null`, hero tanpa kartu, Desire punya 3 kartu.
- Framework lain → kartu untuk `benefits`/`offer`, tabel untuk `comparison`, quote untuk `proof`, bullets untuk section naratif.

Contoh disuntik ke prompt LP dengan instruksi:

```text
COPY THIS SHAPE EXACTLY for aida-1 (replace every placeholder value, keep the key names and the number of sections...)
```

Contoh tetap tidak memuat `design_id`, `section_id`, `image_slot`, `hint`, atau `layout`—semua itu diisi plugin.

## 3. Log lebih bersih

| Sebelum | Sesudah |
|---|---|
| `Job … dimulai / selesai` sebagai info | Dipindah ke level `debug`, tersembunyi di tampilan default |
| `0 Success` walaupun indexing berhasil | RSS/IndexNow sukses dicatat sebagai `success` |
| Hapus log lalu menulis “Log dibersihkan” | Log cleanup dihapus; tidak menambah baris baru |
| Tidak ada filter debug | Filter **Debug (detail job)** ditambahkan |

Statistik sekarang punya enam kategori: Total, Info, Success, Warning, Error, Debug.

Untuk melihat detail per job/attempt, pilih filter **Debug**. Untuk membersihkan, pakai **Hapus sesuai filter** atau **Hapus semua log**.

## 4. Verifikasi

```text
php tests\run.php → 882 passed, 0 failed
node tests\test-admin-runner.js → PASS
php -l → No syntax errors
```

Regression baru: WIB display, AIDA table/quote stripping, contoh JSON AIDA 4 section tanpa table/quote.
