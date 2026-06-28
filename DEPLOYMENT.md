# Deployment Checklist

## Pre-Deployment

- [ ] **Turn off Bubble** — put the Bubble app in maintenance mode
- [ ] **Provision VPS** — Ubuntu 24.04, PHP 8.3+, MySQL 8+, Node 22, Nginx, Composer, Supervisor
- [ ] **Point DNS** — A record to server IP

## Data Migration (Staging)

Run on the staging server **before** deployment:

- [ ] **Export applicant data** — preserves current applicants, interviews, references, etc.

```bash
php artisan app:migrate-applicants-data --export
```

This creates `storage/app/migration.json`. Copy this file to the production server.

## Data Import (Production)

Run on the production server in order:

- [ ] **Import Bubble data**

```bash
php artisan import:bubble-database
php artisan import:staged-data
```

- [ ] **Restore applicant data**

```bash
php artisan app:migrate-applicants-data storage/app/migration.json
```

- [ ] **Download profile photos**

```bash
php artisan import:bubble-photos
```

- [ ] **Backfill caregiver assignments**

```bash
php artisan app:backfill-assignments
```

- [ ] **Clear dummy phone numbers**

```bash
php artisan app:clear-dummy-phones
```

- [ ] **Set resident client types** — requires CSV of resident emails

```bash
php artisan clients:set-resident-type
```

- [ ] **Import trustline certifications** — requires CSV of caregiver names

```bash
php artisan caregivers:import-trustline
```

- [ ] **Parse children from cached AI**

```bash
php artisan app:parse-children --from-cache
```

- [ ] **Sync caregiver ratings** — backfill the cached `caregivers.rating` column from `booking_ratings` (idempotent, safe to re-run)

```bash
php artisan app:sync-caregiver-ratings
```

## Environment Configuration

- [ ] **Generate `APP_KEY`**

```bash
php artisan key:generate --force
```

- [ ] **Application**

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

- [ ] **Database**

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=sitterwise
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

- [ ] **Session**

```
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
```

- [ ] **Cache & Queue**

```
CACHE_STORE=database
QUEUE_CONNECTION=database
```

- [ ] **Mail** — SendGrid

```
MAIL_MAILER=sendgrid
SENDGRID_API_KEY=your_key
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="Sitterwise"
```

- [ ] **Stripe** — live keys

```
STRIPE_KEY=pk_live_xxx
STRIPE_SECRET=sk_live_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
```

- [ ] **Twilio**

```
TWILIO_ACCOUNT_SID=xxx
TWILIO_AUTH_TOKEN=xxx
TWILIO_PHONE_NUMBER=+1xxx
TWILIO_MESSAGING_SERVICE_SID=xxx
```

- [ ] **Google Places**

```
GOOGLE_PLACE_API_KEY=xxx
```

- [ ] **AI Parser**

```
AI_PARSER_API_KEY=xxx
AI_PARSER_MODEL=nvidia/nemotron-4-340b-instruct
AI_PARSER_API_URL=https://openrouter.ai/api/v1/chat/completions
```

- [ ] **VAPID** — generate keys for push notifications

```
VAPID_PUBLIC_KEY=xxx
VAPID_PRIVATE_KEY=xxx
VAPID_SUBJECT=mailto:admin@your-domain.com
```

- [ ] **Logging**

```
LOG_LEVEL=warning
```

## Deployment

```bash
# 1. Clone / push code to server

# 2. Install PHP dependencies
composer install --no-dev --optimize-autoloader

# 3. Copy .env with production values (see above)

# 4. Generate application key
php artisan key:generate --force

# 5. Run migrations
php artisan migrate --force

# 6. Create storage link
php artisan storage:link

# 7. Build frontend
npm ci && npm run build

# 8. Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 9. Run data import commands (see Data Import section above)
```

## Nginx Configuration

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Supervisor Configuration

Queue worker daemon:

```ini
[program:sitterwise-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --tries=3
autostart=true
autorestart=true
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/worker.log
```

## Crontab

```cron
* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
```

## Post-Launch Verification

- [ ] Configure Stripe webhook → `{APP_URL}/stripe/webhook`
- [ ] Verify email delivery (SendGrid)
- [ ] Test booking flow end-to-end
- [ ] Test push notifications
- [ ] Test Twilio SMS delivery
- [ ] Set up backups — database + `storage/app/`
- [ ] Monitor logs for errors

## Time Estimation

### DNS Propagation

`sitterwise.io` is proxied through Cloudflare, so DNS updates propagate in **~0–2 minutes** — no need to wait on TTL. The registrar is Namecheap (SOA TTL: 3600s), but since Cloudflare handles DNS, origin IP changes in the Cloudflare dashboard take effect nearly instantly.

### Deployment Commands (sequential, single run)

| Step | Est. Time | Notes |
|------|-----------|-------|
| `composer install && npm ci && npm run build` | **5–10 min** | Dependency install + Vite build |
| `php artisan migrate --force` | **< 1 min** | Schema-only, no seeders |
| `php artisan config:cache && route:cache && view:cache` | **< 30 sec** | |
| `import:bubble-database` | **40–90 min** | Headless Chrome scraping 32K records from Bubble UI (worst bottleneck) |
| `import:staged-data` | **8–20 min** | 10 DB passes inserting 32K records into app tables |
| `import:bubble-photos` | **15–45 min** | ~1,451 sequential HTTP downloads + image processing |
| `app:migrate-applicants-data` | **< 1 min** | Tiny dataset (~2 applicants) |
| `app:backfill-assignments` | **< 1 min** | Lightweight `firstOrCreate` loop |
| `app:clear-dummy-phones` | **< 10 sec** | String matching on ~6K rows |
| `clients:set-resident-type` | **~1 min** | 943 client updates from CSV |
| `caregivers:import-trustline` | **< 30 sec** | 178 name matches |
| `app:parse-children --from-cache` | **3–10 min** | 16K+ cached AI records (no API calls) |
| `app:sync-caregiver-ratings` | **< 1 min** | Aggregate query + batch update |

### Totals

| Scenario | Time |
|----------|------|
| **Best case** (incremental Bubble scrape + fast servers) | **~45–75 min** |
| **Worst case** (fresh scrape + slow photo servers) | **~2–3 hours** |

The Bubble web scraper (`import:bubble-database`) is the dominant variable — it paginates through 373+ pages with `sleep()` delays. Photos are the next largest chunk. Everything else is fast.
