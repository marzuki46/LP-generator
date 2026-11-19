# LP Generator 1.4.0 — Funnel Lokal

## Contoh: jasa pembuatan website Solo

1. Buat campaign untuk satu offer dan satu LP tujuan.
2. Isi nama offer: Jasa Pembuatan Website Solo. Pilih tipe Jasa.
3. Isi Kota utama LP: Solo. Isi area sekitar yang memang relevan dan dilayani, satu per baris, misalnya Solo, Sukoharjo, dan Karanganyar. Daftar ini adalah konteks dari Anda, bukan bukti cakupan layanan yang telah diverifikasi plugin.
4. Bila LP sudah ada di `/jasa/website/solo/`, pilih mode BOFU yang sudah ada dan pilih halaman tersebut. Pemilihan ini terpisah dari URL CTA/WhatsApp/Checkout.
5. Bila ingin membuat LP baru, pilih mode baru, post type Page dan path `jasa/website/solo`. Halaman induk `jasa/website` harus sudah terbit. Plugin tidak menimpa halaman yang sudah memakai path tersebut.
6. Biarkan Internal Link ke BOFU aktif tanpa batas tanggal. Pilih Selalu BOFU, atau link BOFU + tambahan TOFU ke MOFU. Jika pengaturan link sengaja dimatikan/kedaluwarsa, backlink tidak ditambahkan.
7. Mulai dengan 2 MOFU dan 3 TOFU. Generate, lalu buka Review untuk memeriksa isi, SEO, anchor dan entitas.
8. Approve konten yang sesuai, lalu Publish & Index. Bila ada item gagal/ditahan, baca Activity Log dan regenerate item tersebut.

## Peran masing-masing konten

- TOFU: edukasi yang membantu calon pelanggan lokal memahami kebutuhan website, misalnya informasi yang perlu disiapkan UMKM Solo sebelum membuat website.
- MOFU: pertimbangan dan perbandingan, misalnya memilih website katalog atau toko online untuk usaha di Sukoharjo.
- BOFU: LP penawaran utama, misalnya `/jasa/website/solo/`, berisi layanan, manfaat, proses dan CTA bisnis.
- Semua artikel pendukung memiliki anchor keyword yang dipilih AI untuk menuju LP campaign yang sama. Mode tambahan TOFU–MOFU tetap menyertakan backlink ke BOFU.
- Artikel tag mengikuti aturan entitas dan pemeriksaan duplikat yang sama.

Contoh anchor hanya untuk ilustrasi: “jasa pembuatan website Solo”, “layanan website untuk UMKM Solo”, atau “pengembangan website bisnis di Solo”. Hasil nyata ditentukan AI dan konteks campaign.

## Perubahan perilaku

- Seluruh field campaign tersimpan, termasuk mode BOFU, ID LP, daftar area dan pengaturan linking. Update parsial tidak mengosongkan field lain.
- Setiap LP/artikel mendapat satu permintaan AI tambahan untuk merencanakan 8–12 entitas unik sesuai topik, area dan tahap funnel. Jumlah di luar rentang ditolak. Entitas tersimpan per item dan diteruskan ke prompt penulisan serta pemilihan gambar; tidak dicetak sebagai daftar keyword pada artikel.
- Prompt meminta bahasa natural, ritme kalimat bervariasi, pembahasan konkret, dan melarang bukti/testimoni/fakta lokal yang tidak disediakan. Kualitas hasil AI tetap diperiksa di Review.
- Judul dan isi dibandingkan dengan item seluruh campaign serta post/page tersimpan di situs yang sama, termasuk draft. Pemeriksaan dipaginasi, tidak berhenti pada 100 konten pertama.
- Judul identik setelah normalisasi dan konten identik/terlalu mirip diblokir. Kemiripan menggunakan irisan potongan lima kata dengan ambang 65% terhadap teks yang lebih pendek, minimal 35 potongan unik. Mengganti kota saja dapat tetap terdeteksi.
- Hasil yang diblokir menggunakan retry antrean yang sudah ada, lalu berstatus failed bila tetap gagal. Tidak diterbitkan secara diam-diam. Pemeriksaan dijalankan sebelum menerima hasil AI dan sebelum publish agar duplikasi dari edit manual juga tertahan.
- Ini pemeriksaan kesamaan teks pada situs ini, bukan pemeriksaan plagiarisme seluruh internet dan bukan jaminan bahwa seluruh parafrasa bermakna sama dapat dideteksi.
- Topik sumber boleh memiliki judul sama dengan artikel turunannya sendiri. Item itu dan tujuan publish miliknya dikecualikan dari pemeriksaan diri sendiri; item lain tetap diperiksa.
- Konten editor yang direview menjadi sumber publish, sehingga koreksi manual dipertahankan. Link disisipkan pada paragraf yang memuat anchor; bila tidak ada, ditambahkan paragraf transisi. Link tidak disisipkan di dalam link lain.
- Tombol CTA offer langsung hanya ditambahkan otomatis pada LP. Artikel mendukung alur menuju BOFU.
- Artikel ditahan jika topiknya belum siap atau LP target belum benar-benar berstatus publish. Retry job sebelumnya tidak dilompati job konten berikutnya dalam campaign yang sama. Campaign lain dan indexing dapat tetap berjalan.
- Publish ulang item memperbarui post tujuan yang sama. Auto-publish tidak berjalan jika campaign masih memiliki job gagal.

## Penggunaan pada campaign lain

Buat campaign tersendiri untuk LP lain, lalu tentukan LP tujuan, kota, area, audiens dan masalah yang sesuai. Jangan hanya mengganti nama kota pada campaign lama: pembahasan dan kebutuhan pembaca perlu berbeda. Pemeriksaan duplikat tetap berlaku antar-campaign pada situs yang sama.

## Upgrade dan verifikasi

- Paket install: `lp-generator-1.4.0.zip`. Folder di dalam ZIP adalah `lp-generator/`.
- Konten yang telah terbit tidak diubah otomatis. Untuk item lama yang belum memiliki 8–12 entitas per konten, regenerate terlebih dahulu sebelum publish dengan alur baru.
- Path bertingkat memerlukan Page dan halaman induk yang sudah ada; struktur permalink akhir juga mengikuti konfigurasi WordPress di situs target.
- Pemeriksaan lokal: 182 assertion lulus; bootstrap 33 grup hook dan dua callback aktivasi/deaktivasi lulus; empat skenario SQL antrean lulus di SQLite (bukan pengujian integrasi MySQL).
- WordPress nyata, layanan 9Router, hasil visual, dan penerimaan indexing belum diuji langsung. WPCS belum dijalankan karena phpcs tidak tersedia.
- Manifest lokal disiapkan untuk versi 1.4.0. Rilis GitHub/hosting belum dipublikasikan; update otomatis memerlukan upload ZIP dan manifest ke repositori oleh pemiliknya.
