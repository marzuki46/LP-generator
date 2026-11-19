# AIDA 1.5.1 — Desain pertama dan kontrak konten

> **Catatan 1.5.2:** mekanisme kontrak di dokumen ini sudah digeneralisasi ke 12 framework. AIDA tetap memakai layout khusus; 11 preset lain memakai renderer generik yang memetakan konten ke slot kontrak. Lihat [`09-kontrak-desain-12-framework.md`](09-kontrak-desain-12-framework.md).

## Yang diselesaikan

Desain pertama dirilis pada 1.5.1 untuk AIDA. Pada 1.5.2, pola kontrak section ID, slot gambar dan provenance yang sama dipakai untuk seluruh 12 preset. AIDA bukan lagi satu-satunya framework yang terikat kontrak.

AIDA baru memiliki hero Attention, pembahasan Interest, kartu manfaat Desire, FAQ dan Action. Attention menjadi hero itu sendiri, bukan section berulang di bawah headline. Desain menggunakan HTML/CSS asli, bukan gambar mockup.

## Preview dengan data pengguna

Buka `PREVIEW-AIDA-1.5.1.html`. File ini mulai tanpa data usaha contoh. Isi minimal nama penawaran dan headline untuk menampilkan layout, kemudian isi penjelasan, manfaat, CTA, harga bila ada, serta FAQ. Gambar opsional diambil dari file pilihan pengguna dan ditempatkan pada slot yang dipilih.

Preview lokal tidak memanggil AI dan tidak mengarang fakta bisnis. Ia memakai template kosong dari renderer PHP yang sama dengan plugin. Data input diproses di browser, bukan dikirim ke server. Tombol Unduh HTML menghasilkan preview mandiri yang memiliki CSS di dalam file.

Untuk hasil AI yang benar-benar tersimpan pada WordPress:

1. Install/update paket 1.5.1.
2. Isi campaign dan pilih AIDA.
3. Pada pengaturan gambar, pilih gambar hero, kebutuhan dan manfaat secara terpisah dari Media Library, atau gunakan engine AI/Bing sesuai pengaturan.
4. Mulai generate. Framework campaign disiapkan otomatis; AI menulis konten sesuai kontrak desain.
5. Buka Review, perluas item LP dan lihat iframe preview dari konten tersimpan. Item yang sudah published memakai isi post/page publik, termasuk internal link yang sudah ditambahkan.
6. Klik Unduh preview HTML. File ini dapat dimuat melalui tombol Muat preview dari WordPress pada preview lokal.

File preview memakai `noindex` agar tidak tertukar dengan halaman produksi. Publish melalui plugin menggunakan pengaturan WordPress/SEO situs, bukan tag noindex preview. Jangan memakai file preview sebagai halaman produksi tanpa meninjau metadata tersebut.

## Kode desain yang dipakai AI

Kode desain AIDA: `aida-1` (tetap). Preset lain memakai `${slug}-1`, misalnya `pas-1`. Versi plugin saat ini: `1.5.2`.

| Urutan | Section ID | Tugas konten | Slot gambar |
|---|---|---|---|
| 1 | `aida-attention` | Headline dan intro hero | `aida-hero-visual` |
| 2 | `aida-interest` | Kebutuhan, konteks dan masalah | `aida-interest-visual` |
| 3 | `aida-desire` | Manfaat, dengan 3–6 kartu | `aida-desire-visual` |
| 4 | `aida-action` | Ajakan dan langkah berikutnya | Tidak ada |

Server memberi ID ini pada rencana campaign. Pada 1.5.1 AI penulis diminta mengembalikan `design_id`, `section_id`, `image_slot`, urutan, hint dan layout yang cocok; sejak 1.5.3 kunci struktural tersebut diisi server dari kontrak, jadi AI hanya menulis isi. Headline dan isi Attention harus sama dengan title/intro hero. Pada AIDA ini, tabel dan quote terpisah tidak diterima; hero dan Action tidak menerima kartu/bullets tambahan. Konten salah kontrak ditahan dan menggunakan mekanisme retry, bukan diam-diam dipindahkan ke section lain.

Sejak 1.5.2 pemeriksaan struktur (`design_id`, jumlah section, `section_id`, `image_slot`, larangan URL gambar dari AI) berlaku untuk semua kontrak aktif. Aturan AIDA di atas tetap menjadi pembatas tambahan khusus AIDA.

AI tidak boleh memilih URL/attachment gambar sendiri. AI juga tidak boleh mengirim HTML link, img, iframe atau script. Plugin menyusun markup, link dan attachment dari data yang diverifikasi.

Setiap gambar menyimpan slot ID, section ID, campaign ID, kode desain dan sidik data campaign. Slot asing, slot ganda, section salah, campaign lain atau data campaign lama ditolak. Gambar yang tidak berhasil diperoleh tidak dipindahkan ke galeri fallback. Engine upload AIDA mengambil hanya attachment yang dipilih pada slot itu; tidak menebak berdasarkan urutan galeri umum.

Maksimal tiga slot gambar AIDA. Batas jumlah gambar global tetap membatasi permintaan otomatis; slot upload eksplisit yang lebih akhir tetap diperiksa. Jika jumlah global lebih besar dari tiga, kontrak AIDA tetap membatasi ke tiga slot. Framework lain memiliki jumlah slot sesuai kontrak masing-masing. Gambar lama yang tidak memiliki pemetaan slot perlu dibuat ulang untuk menggunakan desain baru.

Validasi ini memastikan penempatan struktural. Relevansi isi foto atau hasil gambar AI/Bing tetap harus ditinjau pengguna; ID slot tidak membuktikan bahwa objek di dalam foto benar atau bahwa pengguna memiliki hak pakainya.

## Standar internal link dan SEO

- Artikel TOFU/MOFU menuju BOFU campaign sendiri dengan anchor AI yang deskriptif dan masuk ke dalam kalimat. URL BOFU diputuskan oleh plugin berdasarkan halaman yang sudah publish.
- Publisher menolak target internal di luar situs WordPress itu. Anchor disisipkan pada batas kata, bukan di tengah kata atau di dalam link yang sudah ada.
- LP AIDA yang dibuat plugin memiliki slot panduan terkait. Ketika artikel TOFU/MOFU dalam campaign yang sama sudah publish, slot ini diperbarui dengan tautan ke artikel tersebut. Maksimal enam panduan ditampilkan. Konten LP di luar slot tersebut dipertahankan.
- Halaman BOFU eksternal/yang dipilih dari halaman lama tidak ditulis ulang otomatis. Artikel tetap mengarah ke halaman BOFU pilihan, tetapi slot panduan terkait hanya diperbarui pada LP AIDA yang dibuat plugin.
- Navigasi dan internal link memakai elemen `<a href>`, bukan aksi JavaScript. Anchor deskriptif dan konteks pembahasan mengikuti [panduan link Google Search Central](https://developers.google.com/search/docs/crawling-indexing/links-crawlable).
- Satu H1 dipakai pada markup LP; section berikutnya H2 dan kartu H3. Mode canvas menghindari tambahan judul artikel dari template tema.
- Default judul/meta description memakai hasil AI khusus tiap konten, bukan satu template campaign berulang. Pengaturan sumber template tetap tersedia di Settings → Konten & SEO.
- Focus keyword mengambil entitas item tersebut terlebih dahulu. Daftar 8–12 entitas tetap digunakan untuk pengembangan isi, bukan dicetak sebagai daftar keyword pada LP.
- Metadata Yoast tetap didukung. Tanpa plugin SEO yang dikenali, plugin menyediakan fallback title dan meta description untuk konten buatannya. Plugin SEO yang sudah mengisi title lebih dahulu dihormati. Filter `lpgen_output_meta_description` dapat mematikan fallback deskripsi bila situs menggunakan integrasi SEO lain.
- Canonical dan sitemap tetap mengikuti WordPress/plugin SEO. Pengaturan larangan indexing situs tidak ditimpa. Tidak ada schema rating, alamat bisnis, testimoni atau klaim hasil yang direkayasa.
- Hero image memakai eager/high priority; gambar di bawah hero memakai lazy loading. Dimensi attachment dimasukkan bila tersedia untuk mengurangi pergeseran layout. Alt upload yang sudah tersedia dipertahankan. Pengguna perlu memastikan alt menggambarkan gambar, bukan mengulang keyword; lihat [panduan gambar Google](https://developers.google.com/search/docs/appearance/google-images).

Ini standar implementasi dan pemeriksaan, bukan jaminan ranking atau indexing. Tidak ada angka kepadatan keyword maupun kuota exact-match anchor yang dipaksakan sebagai aturan Google.

## Upgrade dan CSS

LP lama tidak dirombak otomatis. Sidik framework campaign sekarang memasukkan `design_id`, jadi rencana yang dibuat sebelum 1.5.2 dianggap kedaluwarsa untuk semua preset berkontrak. Jalankan ulang **Siapkan framework campaign**, lalu generate/regenerate LP agar konten dan slot mengikuti kontrak baru. Perubahan data campaign atau pilihan upload juga membatalkan pemetaan lama agar gambar/konten tidak diterbitkan dengan data yang sudah berganti.

CSS dasar tetap pada `public/css/landing-page.css`, sedangkan CSS AIDA ada pada `public/css/aida.css`. CSS tambahan pengguna dimuat setelah keduanya. Mode halaman penuh dan mode mengikuti tema tetap tersedia; belum seluruh tema/plugin pihak ketiga diuji.

## Hasil verifikasi lokal

- 815 assertion lulus pada paket 1.5.2, termasuk kontrak 12 framework, kontrak section, gambar salah/ganda/lintas campaign, pemilihan upload per slot, internal link dan metadata.
- Parser blok dan penyaring HTML resmi WordPress 6.4 menerima 13 mode framework sebagai regresi kompatibilitas; pengujian juga mencakup markup gambar AIDA.
- Preview diuji pada desktop 1440px dan ponsel 390px: input asli formulir, tiga slot gambar, FAQ, navigasi fragment dan ekspor HTML. Tidak ada screenshot yang menjadi deliverable preview.
- Bootstrap dan pemeriksaan sintaks PHP/JavaScript lulus.

Respons AI pada test menggunakan simulasi, bukan panggilan 9Router live. Instalasi WordPress lengkap, tema produksi pengguna, penerimaan indexing serta penilaian visual relevansi gambar belum diuji langsung. phpcs/WPCS tidak tersedia di lingkungan ini.
