# Deploying Tenantism to tenantism.com

This guide assumes `tenantism.com` is hosted on a PHP/MySQL host such as Hostinger.
The domain currently resolves through Hostinger DNS parking nameservers, so the hosting
panel should be the source of truth for both DNS and the website root.

## Server requirements

- PHP 8.3 or newer with the Laravel-required extensions
- MySQL 8
- Composer 2
- Node 20+ if assets are built on the server
- HTTPS enabled for `tenantism.com` and `www.tenantism.com`

## Recommended layout

Keep the Laravel project outside the public web root and point the web root to the
Laravel `public` directory.

Example:

```text
/home/<user>/domains/tenantism.com/rentra
/home/<user>/domains/tenantism.com/public_html -> rentra/public
```

If the host cannot point the document root directly to `rentra/public`, copy only the
contents of `public/` into `public_html/` and update `public_html/index.php` so these
paths point back to the project directory:

```php
require __DIR__.'/../rentra/vendor/autoload.php';
$app = require_once __DIR__.'/../rentra/bootstrap/app.php';
```

Do not expose the Laravel project root as the public web root.

## First deploy

On the server:

```bash
cd /home/<user>/domains/tenantism.com
git clone https://github.com/emmaduddin1/Rentra.git rentra
cd rentra
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
cp .env.example .env
php artisan key:generate
```

Edit `.env` for production:

```dotenv
APP_NAME="Tenantism"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tenantism.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<database_name>
DB_USERNAME=<database_user>
DB_PASSWORD=<database_password>

SESSION_DOMAIN=.tenantism.com
SESSION_SECURE_COOKIE=true
```

Then run:

```bash
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Queue and scheduled work

The app is configured for the database queue. If the host supports cron, add:

```bash
* * * * * cd /home/<user>/domains/tenantism.com/rentra && php artisan schedule:run >> /dev/null 2>&1
```

For shared hosting without a persistent worker, process queued jobs from cron:

```bash
* * * * * cd /home/<user>/domains/tenantism.com/rentra && php artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
```

## Subsequent deploys

```bash
cd /home/<user>/domains/tenantism.com/rentra
php artisan down
git pull --ff-only
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

## DNS

Point both records at the hosting server:

```text
tenantism.com      A      <server_ipv4>
www.tenantism.com  CNAME  tenantism.com
```

If Hostinger keeps `www` as an A record instead of CNAME, use the same server IP for both.
