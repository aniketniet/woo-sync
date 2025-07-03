# WooCommerce Product Sync System

## Setup

1. Clone repo: `git clone https://github.com/yourname/woo-sync.git`
2. Install dependencies: `composer install`
3. Copy `.env.example` to `.env` and configure:
    - Database credentials
    - WooCommerce credentials
4. Generate key: `php artisan key:generate`
5. Generate JWT secret: `php artisan jwt:secret`
6. Run migrations: `php artisan migrate`
7. Seed database: `php artisan db:seed`

## API Endpoints

-   POST `/api/register` - User registration
-   POST `/api/login` - User login
-   GET `/api/products` - List products (authenticated)
-   POST `/api/products` - Create product (authenticated)

## Testing

Use Postman with:

-   Test user: `test@example.com` / `password`
-   Add Authorization header: `Bearer <jwt_token>`
