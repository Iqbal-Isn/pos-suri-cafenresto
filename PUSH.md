# Panduan Monorepo & Git Workflow (`PUSH.md`)

Dokumen ini berisi aturan standar pengelolaan repository **Sistem POS Cafe & Resto** menggunakan pendekatan **Monorepo** (satu repository GitHub untuk `backend`, `frontend`, dan dokumentasi).

---

## 1. Struktur Direktori Utama

Seluruh proyek dikelola dalam **satu repository Git** di level *parent folder* (`suri-cafenresto/`):

```text
suri-cafenresto/                  # Root repository (.git hanya ada di sini)
├── .git/                         # Satu-satunya folder Git
├── .github/
│   └── workflows/                # CI/CD GitHub Actions (backend & frontend)
├── backend/                      # Laravel 13 (REST API)
├── frontend/                     # React JS + Vite (SPA / PWA)
├── docs/                         # Dokumentasi teknis tambahan (ERD, OpenAPI, dll.)
├── .gitignore                    # Root gitignore
├── docker-compose.yml            # Konfigurasi container lokal (opsional)
├── PRD — Sistem POS Cafe & Resto.md
└── PUSH.md                       # Panduan Git & Monorepo ini
```

---

## 2. Aturan Wajib (Anti-Submodule Error)

1. **Hanya boleh ada SATU folder `.git`** yaitu di root `suri-cafenresto/.git`.
2. **Dilarang ada folder `.git` di dalam `backend/` atau `frontend/`.**
   - Saat menginisialisasi Laravel atau React (misal dengan `laravel new backend` atau `npm create vite@latest frontend`), tool tersebut kadang otomatis membuat `.git` lokal di dalam sub-foldernya.
   - **Wajib hapus `.git` di sub-folder sebelum melakukan `git add` di root:**
     ```bash
     rm -rf backend/.git
     rm -rf frontend/.git
     ```
   - Jika lupa dihapus, GitHub akan menganggap folder `backend` atau `frontend` sebagai *submodule* kosong (ikon folder berwarna abu-abu dan isinya tidak ikut ter-upload).

---

## 3. Langkah Inisialisasi & Push Pertama Kali

Jalankan perintah berikut dari **root folder** (`suri-cafenresto/`):

```bash
# 1. Pastikan berada di root project
cd /home/iqbal/Documents/project/suri-cafenresto

# 2. Pastikan tidak ada .git di dalam subfolder (jika folder sudah dibuat)
rm -rf backend/.git frontend/.git

# 3. Inisialisasi Git di root folder
git init
git branch -M main

# 4. Tambahkan semua file dan buat commit pertama
git add .
git commit -m "chore: initial monorepo setup and PRD documentation"

# 5. Hubungkan ke repository GitHub dan push
git remote add origin https://github.com/Iqbal-Isn/pos-suri-cafenresto.git
git push -u origin main
```

---

## 4. Standar `.gitignore`

Meskipun `backend/.gitignore` dan `frontend/.gitignore` tetap ada di masing-masing folder, pastikan file yang bersifat rahasia atau hasil *build* tidak pernah ikut ter-push:

- **Backend (`backend/`):** `vendor/`, `.env`, `storage/*.key`, `node_modules/`
- **Frontend (`frontend/`):** `node_modules/`, `dist/`, `.env`, `.env.local`
- **Root (`/`):** `.DS_Store`, `.idea/`, `.vscode/`, `*.log`

> **Penting:** Selalu sediakan `backend/.env.example` dan `frontend/.env.example` sebagai template konfigurasi tanpa kredensial asli.

---

## 5. Konvensi Commit Message

Gunakan format **Conventional Commits** dengan *scope* (`backend`, `frontend`, `docs`, atau `root`) agar riwayat perubahan di monorepo mudah dilacak:

**Format:**
```text
<tipe>(<scope>): <deskripsi singkat>
```

**Tipe Commit:**
- `feat`: Fitur baru (contoh: `feat(backend): add pin login endpoint for cashier`)
- `fix`: Perbaikan bug (contoh: `fix(frontend): fix rounding calculation on split bill`)
- `chore`: Setup, konfigurasi, dependency (contoh: `chore(root): configure github actions workflow`)
- `docs`: Perubahan dokumentasi (contoh: `docs(prd): update tax calculation business rules`)
- `refactor`: Perbaikan struktur kode tanpa mengubah fitur
- `test`: Penambahan atau perbaikan automated test

**Jika 1 commit mengubah backend dan frontend sekaligus (1 fitur utuh):**
```bash
git commit -m "feat(order): implement hold and resume order on API and POS UI"
```

---

## 6. Strategi Branching

- `main` — Branch utama yang stabil (siap rilis / *production*).
- `develop` — Branch integrasi pengembangan harian.
- `feat/<nama-fitur>` — Branch fitur baru (contoh: `feat/trx-cashier-cart`, `feat/shf-open-close-shift`).
- `fix/<nama-bug>` — Branch perbaikan bug (contoh: `fix/offline-sync-duplicate`).

**Alur kerja harian:**
```bash
# 1. Buat branch fitur dari develop/main
git checkout -b feat/auth-pin-login

# 2. Kerjakan perubahan di backend/ dan/atau frontend/
git status
git add .
git commit -m "feat(auth): implement cashier PIN login API and UI"

# 3. Push branch ke GitHub dan buat Pull Request (PR)
git push -u origin feat/auth-pin-login
```

---

## 7. Panduan CI/CD & Deployment pada Monorepo

Walaupun berada dalam 1 repository, proses pengujian dan deployment tetap dipisah berdasarkan path folder:

1. **GitHub Actions (Path Filtering):**
   - Workflow Backend hanya berjalan jika ada perubahan di `backend/**`.
   - Workflow Frontend hanya berjalan jika ada perubahan di `frontend/**`.
2. **Deployment Frontend (Vercel / Netlify / Cloudflare Pages / VPS):**
   - Atur **Root Directory** pada pengaturan project menjadi `frontend`.
   - Build command: `npm run build` | Output directory: `dist`.
3. **Deployment Backend (VPS Nginx + PHP-FPM):**
   - Arahkan *Document Root* Nginx ke `/path/to/suri-cafenresto/backend/public`.
   - Saat deploy ulang, jalankan script deploy di dalam direktori `backend/` (`composer install`, `php artisan migrate --force`, `php artisan optimize`).
