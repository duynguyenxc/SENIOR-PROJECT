# Veg Buffet (Senior Project) - Local Docker Setup

## Prereqs
- Docker Desktop installed
- Docker Desktop running (daemon must be on)

## Start
From `d:\Senior-Project`:

```bash
docker compose up -d --build
```

## URLs
- Website: `http://localhost:8080`
- phpMyAdmin: `http://localhost:8081`

## Database credentials (local dev)
- Host (from containers): `db`
- Host (from your PC tools): `127.0.0.1`
- Port: `3306`
- DB: `vegbuffet`
- App user: `app` / `apppass`
- Root user: `root` / `rootpass`

## Reset database (re-run init SQL)
Init SQL in `db/init` runs only on first boot of the volume. To reset:

```bash
docker compose down -v
docker compose up -d --build
```

