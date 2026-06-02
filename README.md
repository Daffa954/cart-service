# Cart Service — PHP Laravel Microservice

Microservice bertanggung jawab atas manajemen keranjang belanja (*shopping cart*) dalam arsitektur marketplace NexaMarket.

## Arsitektur

```
Client (React) → API Gateway (Express TS) → Cart Service (PHP Laravel) → PostgreSQL
                                          ↘ Product Service   (validasi stok)
                                          ↘ Order Service     (saat checkout)
```

## Teknologi

| Stack       | Versi  |
|-------------|--------|
| PHP         | ≥ 8.2  |
| Laravel     | 12.x   |
| Database    | PostgreSQL |
| Auth        | JWT (shared secret dengan Auth Service) |
| HTTP Client | Guzzle (via `Http::fake()` di test) |

---

## Struktur Direktori

```
cart-service/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   └── CartController.php     ← 8 endpoint handler
│   │   ├── Middleware/
│   │   │   └── JwtAuthMiddleware.php  ← Validasi Bearer token
│   │   └── Resources/
│   │       ├── CartResource.php
│   │       └── CartItemResource.php
│   ├── Models/
│   │   ├── Cart.php
│   │   └── CartItem.php
│   └── Services/
│       └── CartService.php            ← Seluruh business logic
├── database/
│   ├── migrations/
│   │   ├── ..._create_carts_table.php
│   │   └── ..._create_cart_items_table.php
│   └── seeders/
│       └── CartSeeder.php
├── routes/
│   └── api.php
├── tests/Feature/
│   └── CartTest.php                   ← 15 test cases
└── config/
    ├── services.php   ← URL inter-service
    └── cors.php
```

---

## API Endpoints

Semua endpoint memerlukan header `Authorization: Bearer <JWT>`.

| Method   | Endpoint                    | Deskripsi                          |
|----------|-----------------------------|------------------------------------|
| `GET`    | `/api/cart`                 | Ambil keranjang aktif + items      |
| `POST`   | `/api/cart/items`           | Tambah produk ke keranjang         |
| `PATCH`  | `/api/cart/items/{itemId}`  | Ubah kuantitas item                |
| `DELETE` | `/api/cart/items/{itemId}`  | Hapus item dari keranjang          |
| `POST`   | `/api/cart/promo`           | Terapkan kode promo                |
| `DELETE` | `/api/cart/promo`           | Hapus kode promo                   |
| `DELETE` | `/api/cart`                 | Kosongkan keranjang                |
| `POST`   | `/api/cart/checkout`        | Checkout → kirim ke Order Service  |
| `GET`    | `/api/health`               | Health check (no auth)             |

---

## Request & Response

### POST `/api/cart/items`

**Request:**
```json
{
  "product_id": 10,
  "quantity": 2,
  "notes": "Ukuran L"
}
```

**Response 201:**
```json
{
  "success": true,
  "message": "Produk berhasil ditambahkan ke keranjang.",
  "data": {
    "cart": {
      "id": 1,
      "user_id": 1,
      "status": "active",
      "item_count": 2,
      "subtotal": 37000000,
      "subtotal_fmt": "Rp 37.000.000",
      "total": 37000000,
      "items": [...]
    },
    "item": {
      "id": 1,
      "product_id": 10,
      "product_name": "Laptop Gaming ROG",
      "unit_price": 18500000,
      "unit_price_fmt": "Rp 18.500.000",
      "quantity": 2,
      "subtotal": 37000000,
      "subtotal_fmt": "Rp 37.000.000"
    }
  }
}
```

### POST `/api/cart/checkout`

**Request:**
```json
{
  "shipping_address": "Jl. Sudirman No. 1, Jakarta",
  "payment_method": "transfer_bank",
  "notes": "Tolong dikemas dengan bubble wrap"
}
```
`payment_method` options: `transfer_bank` | `virtual_account` | `credit_card` | `qris`

**Response 201:** Payload dari Order Service (order_id, status, dst.)

---

## Setup Lokal

### 1. Clone & Install

```bash
cd cart-service
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

### 2. Konfigurasi Database

Edit `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=cart_service_db
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

Buat database di PostgreSQL:
```sql
CREATE DATABASE cart_service_db;
```

### 3. Migrasi & Seed

```bash
php artisan migrate
php artisan db:seed          # opsional — data demo
```

### 4. Jalankan Server

```bash
php artisan serve --port=8001
```

Service berjalan di `http://localhost:8001`

---

## JWT — Shared Secret

Cart Service **tidak menerbitkan** token. Token diterbitkan oleh **Auth Service**.

Cart Service hanya **memverifikasi** token menggunakan `JWT_SECRET` yang sama (di-share antar service). Pastikan nilai `JWT_SECRET` di `.env` identik dengan yang digunakan Auth Service.

```
Auth Service  →  issues JWT  →  Client
Client        →  sends JWT   →  API Gateway
API Gateway   →  forwards    →  Cart Service
Cart Service  →  verifies JWT dengan shared secret
```

---

## Menjalankan Test

Test menggunakan SQLite in-memory (tidak perlu PostgreSQL).

```bash
# Semua test
php artisan test

# Hanya cart tests
php artisan test --filter CartTest

# Dengan detail output
php artisan test --filter CartTest --verbose
```

**15 test cases mencakup:**
- ✅ GET cart (kosong & berisi)
- ✅ Auth guard (401 tanpa token)
- ✅ Isolasi antar user (tidak bisa akses cart user lain)
- ✅ Add item (baru & duplikat → increment qty)
- ✅ Validasi stok dari Product Service
- ✅ Update & hapus item
- ✅ Apply & hapus promo code
- ✅ Clear cart
- ✅ Checkout sukses → Order Service
- ✅ Checkout gagal → cart tetap `active`
- ✅ Health check

---

## Inter-Service Communication

| Dependency       | Kapan dipanggil                | Method |
|------------------|-------------------------------|--------|
| Product Service  | `addItem()` — validasi stok   | GET    |
| Product Service  | `updateItem()` — validasi stok| GET    |
| Promotion Service| `applyPromoCode()` — validasi | GET    |
| Order Service    | `checkout()` — buat order     | POST   |

Semua panggilan melewati **API Gateway** (`localhost:3000`).  
Konfigurasi URL ada di `config/services.php` dan `.env`.
