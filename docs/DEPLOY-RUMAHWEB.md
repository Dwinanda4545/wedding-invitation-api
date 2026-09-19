# Deploy ke Rumahweb (Production + DOKU Sandbox)

Domain yang dipakai project ini:

| Aplikasi | URL |
|----------|-----|
| Frontend (React) | `https://wedding-invitation.sanadwi.my.id` |
| Backend (Laravel API) | `https://api.wedding-invitation.sanadwi.my.id` |

Pembayaran amplop digital memakai **DOKU Checkout** (`DOKU_SANDBOX=true`) — tamu memilih metode di halaman DOKU; aman untuk tes tanpa uang real.

---

## Bagian A — Backend (Laravel API)

### A1. Document root subdomain API

Di **cPanel Rumahweb → Domains → Subdomains** (atau Domain → Document Root):

- Subdomain: `api.wedding-invitation.sanadwi.my.id`
- **Document Root** harus mengarah ke folder **`public`** Laravel:

```
/home/sank9295/public_html/api.wedding-invitation.sanadwi.my.id/public
```

Bukan ke root project (tanpa `/public` Laravel tidak jalan).

### A2. File `.env` di server

1. SSH / File Manager → buka folder API (satu level di atas `public`)
2. Copy `.env.production.example` → `.env`
3. Isi nilai yang masih placeholder:

```bash
php artisan key:generate   # jika APP_KEY masih kosong
```

| Variabel | Nilai |
|----------|-------|
| `APP_URL` | `https://api.wedding-invitation.sanadwi.my.id` |
| `FRONTEND_URL` | `https://wedding-invitation.sanadwi.my.id` |
| `DB_*` | dari cPanel → MySQL Databases |
| `DOKU_*` | Client ID + Secret Key dari **DOKU Dashboard** (sandbox) |
| `FLOWKIRIM_*` | Token + base `https://scan.flowkirim.com` (lihat Bagian F) |
| Webhook URL | `https://api.wedding-invitation.sanadwi.my.id/api/doku/notification` |

### A3. Database

cPanel → **MySQL Databases**:

1. Buat database (mis. `sank9295_wedding_invitation`)
2. Buat user + password, assign ALL PRIVILEGES
3. Import backup lokal atau jalankan migration:

```bash
cd /home/sank9295/public_html/api.wedding-invitation.sanadwi.my.id
php artisan migrate --force
```

### A4. Permission storage

```bash
chmod -R 775 storage bootstrap/cache
```

Pastikan folder `storage/app/public` bisa diakses (untuk QR, galeri):

```bash
php artisan storage:link
```

### A5. Deploy via Git (cPanel)

Repo: `wedding-invitation-api` → branch `main`

File `.cpanel.yml` sudah diset (ringan: copy kode + `migrate`, **tanpa** `composer install` tiap deploy). Setelah push ke GitHub:

1. cPanel → **Git Version Control** → **Update from Remote**
2. Lalu **Deploy HEAD Commit** (bukan hanya pull)
3. Atau aktifkan **Automatic Deployment** pada repo

**Penting:**
- File `.env` **tidak** ikut git — buat manual sekali di server.
- Folder `vendor/` di server dipertahankan. Hanya jalankan `composer install --no-dev` (via Terminal/support) jika `composer.json` / dependensi berubah.
- Jika Deploy tetap **queued**/gagal: update manual (File Manager + phpMyAdmin SQL) — lihat troubleshooting di bawah.
- Setelah ganti `DOKU_*` di `.env`, hapus `bootstrap/cache/config.php` lewat File Manager jika config lama masih terpakai.

### A6. Verifikasi API

Buka di browser:

```
https://api.wedding-invitation.sanadwi.my.id/api/health
```

Harus return: `{"ok":true}`

---

## Bagian B — Frontend (React)

### B1. Build lokal (Windows / Laragon)

```bash
cd wedding-invitation-web
npm install
npm run build
```

Build memakai `.env.production`:

```
VITE_API_BASE_URL=https://api.wedding-invitation.sanadwi.my.id
```

Output ada di folder **`dist/`**.

### B2. Upload ke Rumahweb

Upload **isi folder `dist/`** (bukan folder dist-nya) ke document root frontend:

```
/home/sank9295/public_html/wedding-invitation.sanadwi.my.id/
```

Harus ada: `index.html`, folder `assets/`, dll.

### B3. SPA routing (React Router)

Buat / upload file `.htaccess` di document root frontend:

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteRule ^index\.html$ - [L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule . /index.html [L]
</IfModule>
```

Tanpa ini, refresh halaman `/admin/events` akan 404.

---

## Bagian C — DOKU Sandbox di Production

1. Login [DOKU Dashboard](https://dashboard.doku.com) (mode sandbox)
2. Ambil **Client ID** + **Secret Key**
3. Settings → **Webhook** → set Notification URL:

```
https://api.wedding-invitation.sanadwi.my.id/api/doku/notification
```

4. Copy kredensial ke `.env` server API
5. Pastikan `DOKU_SANDBOX=true`

### Aktifkan amplop di admin

1. Login `https://wedding-invitation.sanadwi.my.id/login`
2. Acara → Undangan → Pengaturan → centang **Amplop Digital** → Simpan

### Tes pembayaran

1. Buka link undangan tamu
2. Isi form amplop → redirect ke halaman bayar DOKU **sandbox**
3. Selesaikan pembayaran tes (simulator DOKU)
4. Cek status di Admin → Acara → **Amplop**

---

## Bagian D — Checklist cepat

```
☐ Document root API → .../public
☐ .env production di server (APP_KEY, DB, DOKU sandbox, FLOWKIRIM_*)
☐ FRONTEND_URL = URL publik https (bukan localhost) agar link WA jadi hyperlink
☐ php artisan migrate --force
☐ php artisan storage:link
☐ /api/health → ok
☐ Frontend dist/ ter-upload + .htaccess SPA
☐ Login admin berhasil (Sanctum cookie)
☐ WA Devices: isi session_id + set default di acara
☐ Webhook URL terdaftar di DOKU Dashboard
☐ Section Amplop Digital enabled
☐ Tes 1 transaksi amplop end-to-end
☐ Tes 1 kirim undangan WhatsApp
```

---

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| 500 API | Cek `storage/logs/laravel.log`, permission storage |
| CORS error | `CORS_ALLOWED_ORIGINS` = URL frontend exact (https, no trailing slash) |
| Login gagal / 419 CSRF | `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, HTTPS |
| 503 amplop | DOKU Client ID / Secret Key kosong atau salah |
| Webhook tidak update status | URL webhook di DOKU ≠ `/api/doku/notification`; harus HTTPS publik |
| Refresh 404 di React | Tambah `.htaccess` SPA di frontend |
| Deploy Git stuck **queued** | Batal antrian, Deploy ulang; atau update manual File Manager + SQL migrate. `.cpanel.yml` sudah tanpa `composer install` |
| WA: Could not resolve host api.flowkirim.com | Set `FLOWKIRIM_BASE_URL=https://scan.flowkirim.com` |
| WA: Forbidden device/pool | `session_id` di WA Devices salah / bukan milik akun token |
| Link WA tidak hyperlink | `FRONTEND_URL` harus `https://...` publik, bukan localhost |

---

## Bagian E — Role Admin / Panitia & Buku Tamu

Setelah migration `2026_09_10_040000_add_roles_and_event_user_table`:

1. User existing otomatis `role=admin`
2. Admin membuat panitia di menu **Users** atau tombol **Panitia** per acara
3. Panitia hanya melihat **Scan Check-in** + **Buku Tamu** untuk acara assigned

### Pusher (realtime Buku Tamu)

Di `.env` API:

```env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=ap1
```

Di build frontend (`.env.production`):

```env
VITE_PUSHER_APP_KEY=
VITE_PUSHER_APP_CLUSTER=ap1
```

`VITE_PUSHER_APP_KEY` harus **sama** dengan `PUSHER_APP_KEY` API. Setelah ubah env frontend, **rebuild** `npm run build` lalu upload ulang `dist/`.

Setelah ubah env API, hapus `bootstrap/cache/config.php` lewat File Manager lalu Deploy ulang (atau tunggu cache clear dari `.cpanel.yml`).

**CORS:** `config/cors.php` harus mengizinkan path `broadcasting/*` (auth channel private). Tanpa itu, Buku Tamu di production tidak realtime meski key Pusher sudah benar — lokal tetap jalan karena Vite proxy.

Cek cepat di browser DevTools → Network: request `POST .../broadcasting/auth` harus **200**, bukan CORS error / 403.

**Login vs Pusher:** `BROADCAST_CONNECTION=pusher` tidak memproses login. Jika setelah mengisi Pusher Anda “tidak bisa login”, biasanya cookie session tertimpa saat Echo memanggil `/broadcasting/auth` (sudah diperbaiki ke middleware `api` + `auth:sanctum`). Pastikan saat edit `.env` Anda **tidak** mengubah `APP_KEY` / `SESSION_*` / `SANCTUM_*`, lalu hapus `bootstrap/cache/config.php`.

---

## Bagian F — FlowKirim WhatsApp (production)

1. Di `.env` server API isi:

```env
FLOWKIRIM_API_TOKEN=...
FLOWKIRIM_BASE_URL=https://scan.flowkirim.com
FLOWKIRIM_SEND_PATH=/api/whatsapp/messages/text
FLOWKIRIM_DEVICE_FIELD=session_id
FLOWKIRIM_APPEND_JID_SUFFIX=true
FLOWKIRIM_LINK_PREVIEW=true
FRONTEND_URL=https://wedding-invitation.sanadwi.my.id
```

2. Hapus `bootstrap/cache/config.php` (atau `php artisan config:clear`).
3. Pastikan migration terbaru sudah jalan (`invitation_sends`, `whatsapp_devices`, kolom `whatsapp_device_id`).
4. Login admin production → **WA Devices** → isi `session_id` dari [scan.flowkirim.com](https://scan.flowkirim.com).
5. Di **Acara** → set device pengirim default.
6. Tes **Kirim WA** dari halaman Tamu.

**Catatan link hyperlink:** `FRONTEND_URL` harus domain HTTPS publik. `localhost` tidak akan jadi hyperlink biru di WhatsApp.

---

## Nanti go-live DOKU (uang real)

1. Aktifkan akun production di DOKU Dashboard
2. Update `.env`:

```env
DOKU_SANDBOX=false
DOKU_CLIENT_ID=...
DOKU_SECRET_KEY=...
DOKU_NOTIFICATION_PATH=/api/doku/notification
```

3. `php artisan config:clear && php artisan config:cache`
