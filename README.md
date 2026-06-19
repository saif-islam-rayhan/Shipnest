# ShipNest

Industry-level multi-vendor e-commerce platform for Bangladesh — inspired by Daraz.

## Tech Stack

- **Backend:** Laravel 11, PHP 8.3
- **Database:** MySQL 8
- **Cache & Queue:** Redis (Laravel Horizon)
- **Search:** Laravel Scout + MeiliSearch
- **Frontend:** Blade, Alpine.js, Tailwind CSS v3
- **Auth:** Laravel Sanctum
- **Payments:** SSLCommerz, bKash, Nagad, Cash on Delivery

## Features

- Multi-vendor marketplace with shop approval workflow
- Role-based access: `super_admin`, `admin`, `merchant`, `customer`
- Product catalog with categories, brands, images, and MeiliSearch indexing
- Shopping cart with guest & authenticated sessions
- Multi-shop checkout (orders split per vendor)
- Payment gateway integrations (SSLCommerz, bKash, Nagad, COD)
- Merchant seller center (products, orders, shop management)
- Admin panel (users, shops, orders, approvals)
- Daraz-inspired UI with Primary `#F57C00` and Secondary `#1A237E`

## Requirements

- PHP 8.3+
- Composer
- Node.js 18+
- MySQL 8
- Redis
- MeiliSearch (optional, for search)

## Installation

```bash
# Clone and install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Configure MySQL, Redis, and MeiliSearch in .env

# Database
php artisan migrate --seed
php artisan storage:link

# Build assets
npm run build

# Start development (server, queue, logs, vite)
composer dev
```

## Default Accounts (after seeding)

| Role | Email | Password |
|------|-------|----------|
| Super Admin | admin@shipnest.com | password |
| Merchant | merchant@shipnest.com | password |
| Customer | customer@shipnest.com | password |

## Architecture

```
app/
├── Enums/          # UserRole, OrderStatus, PaymentMethod, etc.
├── Http/
│   ├── Controllers/
│   │   ├── Admin/       # Platform administration
│   │   ├── Auth/        # Login & registration
│   │   ├── Merchant/    # Seller center
│   │   └── Storefront/  # Customer-facing shop
│   ├── Middleware/      # Role & active user checks
│   └── Requests/        # Form request validation
├── Models/         # Eloquent models with eager loading
├── Services/       # Business logic (Cart, Order, Product, Payment)
└── Traits/         # HasSlug, etc.
```

## Payment Configuration

Set sandbox credentials in `.env` for each gateway:

- `SSLCOMMERZ_*` — SSLCommerz sandbox/production
- `BKASH_*` — bKash tokenized checkout
- `NAGAD_*` — Nagad merchant API

## License

MIT
# Shipnest
# Shipnest
