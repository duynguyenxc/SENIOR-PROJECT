# Veg Buffet

[![Live Demo](https://img.shields.io/badge/Live%20Demo-veg--buffet--web.onrender.com-2e7d32?style=for-the-badge)](https://veg-buffet-web.onrender.com/)

Veg Buffet is a restaurant ordering web app built for a senior project. The idea behind it is simple: customers should be able to browse a weekly vegetarian menu, order takeout online, and track their orders, while staff and admins get a separate internal workflow for managing orders, menu items, takeout sets, and reports.

The live project is currently available here: [https://veg-buffet-web.onrender.com/](https://veg-buffet-web.onrender.com/)

## What the project includes

- Customer account registration and login
- Weekly buffet menu by day
- Takeout ordering with standard sets and a custom takeout box
- Cart, checkout, and Stripe sandbox payment flow
- Order tracking for customers
- Staff dashboard for live order handling
- Super admin tools for menu, takeout sets, staff accounts, and reports
- Basic sales analytics for daily, weekly, and monthly views

## User roles

The system is split into three practical roles:

- Customer: browses the menu, places takeout orders, pays, and checks order history
- Staff: views incoming orders and updates order status
- Super Admin: has full internal access, including staff management, menu management, takeout management, and reports

## Tech stack

- PHP 8.3
- Apache
- MariaDB / MySQL-compatible database
- Docker Compose for local development
- Stripe Checkout (test mode)
- Render for web hosting
- TiDB Cloud for the external database used in the free deployment setup

## Project structure

```text
db/init/             Database schema and seed files
web/                 PHP application
web/admin/           Internal staff and admin pages
web/lib/             Shared business logic helpers
web/assets/          CSS and JavaScript assets
docker-compose.yml   Local development stack
render.yaml          Render Blueprint for deployment
```

## Running the project locally

### Requirements

- Docker Desktop installed
- Docker Desktop running

### Start the app

From the project root:

```bash
docker compose up -d --build
```

### Local URLs

- Website: `http://localhost:8080`
- phpMyAdmin: `http://localhost:8081`

### Local database

- Container host: `db`
- Local machine host: `127.0.0.1`
- Port: `3306`
- Database: `vegbuffet`
- App user: `app`
- App password: `apppass`
- Root user: `root`
- Root password: `rootpass`

### Reset the local database

The SQL files in `db/init/` only run when the database volume is created for the first time. If you want a clean reset:

```bash
docker compose down -v
docker compose up -d --build
```

## Default local internal account

The seeded local super admin account is:

- Email: `admin@vegbuffet.com`
- Password: `password`

Staff accounts are created from inside the admin dashboard.

If you are using this project outside a classroom/demo setting, change the default credentials immediately.

## Deployment notes

This repository is set up to support a free Render deployment for the web app. Because Render free services do not provide private MySQL hosting or persistent disk storage, the live deployment uses:

- Render for the PHP web service
- TiDB Cloud as the external MySQL-compatible database

### Deployment files

- `render.yaml` creates the Render web service
- `web/Dockerfile` builds the production image
- `web/render-start.sh` prepares the uploads directory before Apache starts

### Database setup for deployment

Before deploying, create an external MySQL-compatible database and import:

- `db/init/01_schema.sql`
- `db/init/02_seed.sql`

For the current free-tier setup, TiDB Cloud works well because it is MySQL-compatible and supports the connection model this app needs.

### Environment variables used in deployment

- `APP_URL`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_SSL_CA_PATH`
- `DB_SSL_VERIFY_SERVER_CERT`
- `STRIPE_SECRET_KEY`
- `STRIPE_PUBLISHABLE_KEY`

### Render free-tier limitations

There are a few trade-offs with the current free deployment:

- The app can be slow on the first request after inactivity because free Render services go to sleep
- Uploaded files are not permanently reliable on free storage
- The app depends on an external database connection, so network latency is higher than a fully paid single-platform setup

Even with those limits, the current deployment is good enough for project review, demonstrations, and functional testing.

## Notes on payment

Stripe is configured in test mode for this project. The payment flow is intended for demonstration and development, not for real production billing.

If Stripe keys are not provided, the app can fall back to a mock-style payment flow depending on the deployment configuration.

## Why the database design looks the way it does

The database is organized around a few core ideas:

- `Admin` and `Customer` handle authentication for internal users and customers separately
- `Day`, `Dish`, and `DayMenuItem` control the weekly buffet menu
- `TakeoutSet` stores the takeout products shown to customers
- `Order` stores the order header, while `OrderItem` stores each line item
- `Payment` stores payment status and references separately from order fulfillment status

This keeps the app easier to maintain and makes reporting and order tracking more straightforward.

## Current live link

If you just want to open the deployed version, use this link:

[https://veg-buffet-web.onrender.com/](https://veg-buffet-web.onrender.com/)

