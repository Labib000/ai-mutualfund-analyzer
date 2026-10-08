# Hisaab

A personal mutual fund tracker for Indian investors. Add the funds you hold and your SIPs; Hisaab fetches official AMFI NAVs, calculates returns including XIRR, and explains your portfolio in plain language.

> Hisaab analyzes and explains. It never gives investment advice.

**Status:** in development. See [PLAN.md](PLAN.md) for the roadmap.

## Stack

- Laravel 13 + React (TypeScript) via Inertia.js, built on the Laravel React starter kit
- Tailwind CSS 4, shadcn/Radix UI, Recharts
- MariaDB, database queue driven by the scheduler
- Razorpay (one-time payments) and an LLM provider behind interfaces
- Runs on shared hosting (Hostinger): no Redis, no Docker, no long-running workers

## Local setup

Requirements: PHP 8.3+ (with `pdo_mysql`, `bcmath`, `intl`), Composer, Node 22+, MariaDB or MySQL.

```bash
# Create the databases
mysql -uroot -e "CREATE DATABASE hisaab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                 CREATE DATABASE hisaab_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Install, configure, migrate and build
composer setup

# Run the app (server, Vite, logs)
composer dev
```

Edit `.env` if your database credentials differ from `root` with no password.

## Tests and checks

```bash
composer test       # Pint, Larastan, PHPUnit (uses the hisaab_test database)
composer ci:check   # everything CI runs, including frontend lint and tsc
```

## Scheduler and queue

Production uses a single cron entry:

```
* * * * * cd /path/to/hisaab && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler drains the database queue every minute (`routes/console.php`), so no separate worker process is needed.

## Architecture

_To be written._

## Decisions

_To be written. Planned topics: money as integer paise, fixed-precision NAVs and units, the webhook as the payment source of truth, cron-driven queues on shared hosting, and why the AI never gives advice._
