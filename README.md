# Cacti Docker Stack

Deploy Cacti dengan Docker Compose, Caddy, Apache/PHP, dan MySQL dalam satu folder. Project ini dibuat supaya instalasi Cacti bisa dijalankan lebih rapi, mudah dipindah, dan konfigurasinya cukup diatur lewat environment variables.

## Fitur

- Cacti berjalan di container Apache/PHP custom.
- MySQL 5.7 disiapkan sebagai database backend.
- Caddy dipakai sebagai reverse proxy dan entry point domain.
- phpMyAdmin tersedia untuk administrasi database.
- Konfigurasi database dipusatkan di file `.env`.

## Arsitektur

- `apache-php` menjalankan aplikasi Cacti.
- `mysql` menyimpan data Cacti.
- `caddy` menerima request dari domain dan meneruskan ke Apache.
- `phpmyadmin` dipakai untuk mengelola database secara visual.

## Prasyarat

- Docker
- Docker Compose
- Domain atau subdomain yang mengarah ke server

## Struktur Proyek

- `docker-compose.yml` untuk orkestrasi service.
- `Dockerfile` untuk build image PHP Cacti.
- `Caddyfile` untuk reverse proxy domain.
- `.env` untuk kredensial dan konfigurasi runtime.
- `cacti/` berisi source Cacti.
- `cacti.sql` berisi initial schema dan data database.

## Instalasi

1. Clone repository ini.
2. Pastikan file `.env` sudah berisi konfigurasi yang sesuai.
3. Sesuaikan domain di [Caddyfile](Caddyfile) jika perlu.
4. Jalankan stack dengan:

```bash
docker compose up -d --build
```
5. tambahkan crontab dengan perintah crontab -e lalu masukan crontab di bawah ini
```bash
*/5 * * * * docker exec apache-php php /var/www/html/poller.php > /dev/null 2>&1
*/5 * * * * docker exec apache-php php /var/www/html/cmd.php > /dev/null 2>&1
```
6. Akses aplikasi Cacti melalui domain yang sudah diarahkan ke server.
7. Akses phpMyAdmin melalui `http://localhost:8081` atau port yang kamu buka di server.

## Konfigurasi Environment

File `.env` digunakan untuk menyimpan nilai yang ingin diganti tanpa mengubah source code. Variabel yang dipakai saat ini:

- `TZ` untuk timezone container.
- `MYSQL_ROOT_PASSWORD` untuk password root MySQL.
- `MYSQL_DATABASE` untuk nama database Cacti.
- `MYSQL_USER` untuk user database Cacti.
- `MYSQL_PASSWORD` untuk password user database Cacti.
- `CACTI_DB_HOST` untuk host database yang dipakai Cacti.
- `CACTI_DB_USER` untuk username database Cacti.
- `CACTI_DB_PASSWORD` untuk password database Cacti.
- `CACTI_DB_PORT` untuk port database.

Kalau kamu mau ganti password atau host, cukup ubah di `.env`, lalu restart container:

```bash
docker compose down
docker compose up -d --build
```

## Catatan Penting

- Jangan commit `.env` jika berisi credential asli.
- Jika volume MySQL sudah pernah terbuat, mengubah password di `.env` saja belum tentu mengubah user yang sudah tersimpan di database.
- Jika database sudah jalan lama, kamu mungkin perlu update user MySQL atau inisialisasi ulang volume.

## Akses Layanan

- Cacti: domain yang ada di [Caddyfile](Caddyfile)
- phpMyAdmin: `http://localhost:8081`
- MySQL: port `3306`

## Troubleshooting

- Pastikan DNS domain sudah mengarah ke IP server.
- Pastikan port `80` dan `443` tidak dipakai service lain.
- Jika Cacti gagal konek ke database, cek isi `.env` dan container MySQL.
- Jika build PHP gagal, cek dependency di [Dockerfile](Dockerfile).

## License

Project ini mengikuti lisensi dari komponen Cacti yang dipakai di dalamnya. Silakan cek lisensi asli dari Cacti sebelum redistribusi.
