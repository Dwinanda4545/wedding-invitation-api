# Deploy ke Rumahweb (Production + Duitku Sandbox)

Domain yang dipakai project ini:

| Aplikasi | URL |
|----------|-----|
| Frontend (React) | `https://wedding-invitation.sanadwi.my.id` |
| Backend (Laravel API) | `https://api.wedding-invitation.sanadwi.my.id` |

Pembayaran amplop digital tetap **Duitku Sandbox** (`DUITKU_SANDBOX=true`) — aman untuk tes di server live tanpa uang real.

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
| `DUITKU_*` | Merchant Code + API Key dari **sandbox.duitku.com** |
| `DUITKU_CALLBACK_URL` | `https://api.wedding-invitation.sanadwi.my.id/api/duitku/callback` |

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

File `.cpanel.yml` sudah diset. Setelah push ke GitHub:

1. cPanel → **Git Version Control** → Pull / Deploy
2. Atau aktifkan **Automatic Deployment** pada repo

**Penting:** File `.env` **tidak** ikut git — buat manual sekali di server.

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

## Bagian C — Duitku Sandbox di Production

1. Login [https://sandbox.duitku.com](https://sandbox.duitku.com)
2. **My Project** → buat/edit project
3. **Callback URL** (wajib sama persis):

```
https://api.wedding-invitation.sanadwi.my.id/api/duitku/callback
```

4. Copy **Merchant Code** + **API Key** sandbox ke `.env` server API
5. Pastikan `DUITKU_SANDBOX=true`

### Aktifkan amplop di admin

1. Login `https://wedding-invitation.sanadwi.my.id/login`
2. Acara → Undangan → Pengaturan → centang **Amplop Digital** → Simpan

### Tes pembayaran

1. Buka link undangan tamu
2. Isi form amplop → redirect ke halaman bayar Duitku **sandbox**
3. Selesaikan pembayaran tes
4. Cek status di Admin → Acara → **Amplop**

---

## Bagian D — Checklist cepat

```
☐ Document root API → .../public
☐ .env production di server (APP_KEY, DB, Duitku sandbox)
☐ php artisan migrate --force
☐ php artisan storage:link
☐ /api/health → ok
☐ Frontend dist/ ter-upload + .htaccess SPA
☐ Login admin berhasil (Sanctum cookie)
☐ Callback URL terdaftar di Duitku sandbox
☐ Section Amplop Digital enabled
☐ Tes 1 transaksi amplop end-to-end
```

---

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| 500 API | Cek `storage/logs/laravel.log`, permission storage |
| CORS error | `CORS_ALLOWED_ORIGINS` = URL frontend exact (https, no trailing slash) |
| Login gagal / 419 CSRF | `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, HTTPS |
| 503 amplop | Duitku key salah atau kosong |
| Callback tidak update status | Callback URL di Duitku ≠ `.env`; harus HTTPS publik |
| Refresh 404 di React | Tambah `.htaccess` SPA di frontend |

---

## Nanti go-live Duitku (uang real)

1. Verifikasi akun di [passport.duitku.com](https://passport.duitku.com)
2. Buat project **production**
3. Update `.env`:

```env
DUITKU_SANDBOX=false
DUITKU_MERCHANT_CODE=...
DUITKU_API_KEY=...
DUITKU_CALLBACK_URL=https://api.wedding-invitation.sanadwi.my.id/api/duitku/callback
```

4. `php artisan config:clear && php artisan config:cache`
