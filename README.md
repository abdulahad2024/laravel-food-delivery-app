# FoodFlow - Food Delivery Platform

This project is a Laravel-based food delivery monolith foundation that includes a modular backend structure, role-based authentication, API endpoints, and an admin dashboard for a multi-vendor food delivery platform.

## Implemented foundation

- Laravel 13 application skeleton
- Sanctum-based API authentication
- Role and permission models with user-role assignment
- Restaurant, category, food, address, order, and order item models
- REST API at `/api/v1`
- Admin dashboard at `/admin`
- Seeded demo users and restaurant data
- Automated feature tests for auth and restaurant discovery

## Demo accounts

- Super admin: `admin@foodflow.test` / `password123`
- Restaurant owner: `owner@foodflow.test` / `password123` (stored in `restaurant_users`)
- Customer: `customer@foodflow.test` / `password123`

## Local setup

1. Install dependencies:
   ```bash
   composer install
   npm install
   ```
2. Copy environment file and generate app key:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
3. Create and migrate the database:
   ```bash
   php artisan migrate --seed
   ```
4. Run the application:
   ```bash
   php artisan serve
   ```

## API overview

Authentication:
- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`

Restaurant-owner authentication uses the separate `restaurant_users` table:

- `POST /api/v1/restaurant/auth/register` — requires `name`, `email`, `password`, `password_confirmation`, and `restaurant_name`; creates a pending restaurant.
- `POST /api/v1/restaurant/auth/login` — returns a restaurant API token.
- `GET /api/v1/restaurant/auth/me`
- `PATCH /api/v1/restaurant/auth/profile`
- `POST /api/v1/restaurant/auth/logout`
- `GET /api/v1/restaurant/profile` — returns the complete restaurant profile.
- `PATCH /api/v1/restaurant/profile` — updates restaurant details; accepts `profile_image` as a JPG, PNG, or WebP upload (maximum 2 MB). Set `remove_profile_image=1` to remove it.
- `POST /api/v1/admin/restaurants` and `PATCH /api/v1/admin/restaurants/{restaurant}` also accept `profile_image` uploads; public restaurant list/detail responses include the stored `profile_image` path and `profile_image_url`.

Send image changes as `multipart/form-data`; send `Authorization: Bearer <token>` for protected endpoints. Restaurant image uploads use the public storage disk. Run `php artisan storage:link` once after deploying to make uploaded images reachable at their returned `profile_image_url`. Restaurant accounts use the `/api/v1/restaurant/...` endpoints for their restaurant profile, orders, categories, foods, settlements, and payout ledger. Customer, rider, and administrator accounts remain in `users` and cannot use restaurant-owner endpoints. Administrators can manage restaurant accounts from `/admin/restaurant-users` or the `/api/v1/admin/restaurant-users` API.

Restaurant discovery:
- `GET /api/v1/restaurants`
- `GET /api/v1/restaurants/{restaurant}`

## Web admin panel

Start the Laravel server and sign in at `/login` with an active administrator account. For local development, seed the demo account:

```text
Email: admin@foodflow.test
Password: password123
```

The panel at `/admin` reads live database records and includes:

- Dashboard metrics and recent orders
- User search, role assignment, account status and account creation
- Separate restaurant-account management, including credentials and account status
- Restaurant approval, profile/status management and creation
- Menu item creation, price, featured and availability management
- Order search, date/status filters and allowed status transitions
- Payment history and refund review
- Coupon creation, editing, activation and removal
- Settlement approval/payment and CSV exports
- Payout ledger filters and CSV exports

Changes to financial statuses are persisted together with their related commission and payout-ledger records. User, restaurant and menu records are not hard-deleted from the panel so historical orders remain intact. Payment history is an audit view; a live payment gateway is not configured by this project.

The demo credentials are only for local development. Change or remove them before deploying the app.

## Testing

Run the project test suite:

```bash
php artisan test
```

## Notes for Bengali setup guidance

- এই প্রজেক্টের ডেমো ডেটা লোকাল ডেভেলপমেন্টের জন্য তৈরি করা হয়েছে; production credential কখনো hardcode করবেন না।
- আপডেট বা নতুন মডিউল যোগ করার সময় `php artisan migrate` ও `php artisan test` চালিয়ে নিশ্চিত করুন।
- Web admin-এর order, restaurant, user, menu, refund, coupon, settlement ও payout ledger data database থেকে আসে; এগুলো static design নয়।
- Real payment gateway, automated refund transfer এবং production deployment-এর credential এখনো আলাদা করে configure করতে হবে।
