# Perbaikan Generate — LP Generator 1.5.2

## Penyebab yang ditemukan

Tombol Generate dan Siapkan framework campaign memakai `data.lpgen = formValues` sebelum objek JavaScript `data` dibuat. Browser berhenti dengan error:

```text
Cannot set properties of undefined (setting 'lpgen')
```

Karena berhenti sebelum mengirim permintaan, WordPress belum membuat antrean. Gejalanya sesuai laporan: status draft, antrean 0/0 dan progres 0%.

Pada 19 November 2026, file JavaScript publik yang diambil dari [cahsolo.my.id](https://cahsolo.my.id/wp-content/plugins/lp-generator/admin/js/admin.js) sama persis dengan source 1.5.1 lokal sebelum perbaikan. SHA-256 keduanya:

```text
1064ECD9EB4DAF18B66EFAF55ADDFCCCC855AB8339F738FFD3ADE50C41EF2EF0
```

Halaman admin dan data campaign live tidak dapat diakses dari koneksi yang tersedia. Pemeriksaan ini tidak menggunakan password atau mengambil kredensial pengguna.

## Perubahan pada versi 1.5.2

- Objek permintaan dibuat sebelum serialisasi form maupun pengiriman AJAX.
- Generate pada dashboard memakai data campaign tersimpan; form edit mengirim perubahan field sebelum antrean dibuat.
- Tombol dikembalikan ke keadaan aktif jika pembuatan antrean gagal.
- Permintaan gagal, sesi/izin tidak valid, konfigurasi AI kosong dan kegagalan server mendapatkan pesan yang terlihat.
- Satu campaign tidak menjalankan beberapa loop browser secara bersamaan.
- Status dan progres baris campaign diperbarui selama proses berlangsung.
- Label awal draft pada dashboard diubah menjadi Belum digenerate. Ini berbeda dari status post WordPress yang menyimpan brief campaign.
- Publish & Index juga menjalankan antrean di halaman terbuka, sehingga proses immediate tidak hanya bergantung pada WP-Cron.
- Worker tidak melaporkan done ketika masih ada job running. Antrean kosong tidak diklaim berhasil.
- Setelah finalisasi generation, worker menghitung ulang antrean sehingga auto-publish tidak berhenti sebelum job publish yang baru dibuat dijalankan.
- Parsing jadwal fixed diperbaiki agar waktu pada input tanggal/jam tidak ditambahkan dua kali dan berubah menjadi secepatnya. Pengujian tanggal harian juga diperbaiki agar memakai format tanggal WordPress yang benar.
- Jadwal yang masih jauh ditampilkan sebagai menunggu jadwal; antreannya tidak dihapus.
- Kegagalan memasukkan job ke database tidak lagi diklaim sebagai pembuatan antrean yang berhasil.
- Kegagalan PHP yang dapat ditangkap dikembalikan sebagai respons kesalahan terstruktur. Pesan teknis sensitif tidak dikirim mentah ke browser.

Rilis 1.5.2 berfokus pada perbaikan alur. Desain AIDA 1.5.1 dan kontrak gambarnya tetap dipertahankan, lalu mekanisme kontrak tersebut digeneralisasi ke 12 preset pada paket yang sama; lihat [`09-kontrak-desain-12-framework.md`](09-kontrak-desain-12-framework.md). Rencana framework lama perlu dibuat ulang setelah pembaruan.

## Pengujian yang dijalankan

Pada awal perbaikan alur, 394 assertion PHP lulus. Setelah regression kontrak 12 framework ditambahkan, total terbaru adalah **815 assertion lulus**; bootstrap dan pemeriksaan sintaks PHP/JavaScript tetap lulus.

Pengujian browser menggunakan jQuery resmi WordPress, file admin.js sebenarnya, server PHP lokal dan kelas Ajax/Generator/Publisher plugin yang sebenarnya. Penyimpanan WordPress dan respons AI disimulasikan; tidak ada panggilan AI berbayar atau publish pada situs pengguna.

| Skenario browser/PHP | Hasil |
|---|---|
| Script live 1.5.1 sebelum perbaikan | Error berhasil direproduksi, tidak ada job terbentuk |
| Generate dari dashboard, cron otomatis tidak dijalankan | 7 job selesai; 1 LP dan 2 artikel masuk review |
| Approve lalu publish | 1 LP AIDA + 2 artikel terbit pada penyimpanan uji; internal link dan panduan terkait tersedia |
| Sesi/nonce simulasi tidak valid | Pesan terlihat, tombol dapat dicoba ulang |
| Izin simulasi tidak cukup | Pesan terlihat, tidak ada antrean baru |
| Insert database simulasi gagal | Tidak diklaim sukses, tombol dapat dicoba ulang |
| Respons HTTP 500 | Pesan terlihat, tombol tidak terkunci |
| Generate melalui form edit | Input disimpan sebelum generation berjalan |
| Auto-publish | Generation berlanjut ke antrean publish sampai status published |

Pengujian ini membuktikan alur lokal dan reproduksi bug JavaScript. Belum membuktikan koneksi 9Router, kapasitas hosting, WordPress/MySQL sebenarnya atau indexing pada cahsolo.my.id sudah berhasil. Worker foreground berjalan selama halaman proses terbuka; untuk jadwal di masa depan atau browser yang ditutup, WordPress/server cron tetap diperlukan.

## Cara menerapkan pada situs

1. Upload `lp-generator-1.5.2.zip` melalui Plugins → Add New → Upload Plugin, lalu pilih mengganti versi yang terpasang.
2. Pastikan plugin aktif dan versinya 1.5.2.
3. Buka kembali dashboard LP Generator lalu muat ulang dengan Ctrl+F5 agar browser memuat JavaScript baru.
4. Gunakan campaign 1159 yang sama, Jasa Pembuatan Website Solo, lalu klik Generate dan setujui dialog yang muncul.
5. Status seharusnya berubah menjadi Sedang digenerate dan jumlah antrean terisi. Biarkan halaman proses terbuka.
6. Jika muncul pesan konfigurasi AI, lengkapi Settings → AI. Setelah hasil siap, review, approve dan Publish & Index.

Source dan paket disiapkan di folder proyek lokal. Paket ini belum dipasang pada WordPress dan belum dipublikasikan ke GitHub. Versi pada manifest lokal dinaikkan agar cache script menggunakan nomor baru setelah update; fitur update otomatis dari GitHub memerlukan manifest dan ZIP rilis tersebut diunggah ke repository oleh pemiliknya.
