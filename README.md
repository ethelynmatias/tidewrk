## Tech Stack

| Component | Version |
|-----------|---------|
| PHP       | 8.3 (FPM, Alpine) |
| Laravel   | Latest |
| Database  | MySQL 8.0 |
| Cache/Queue | Redis |
| Web server | Nginx |
| Excel     | maatwebsite/excel |

---

## Requirements

- [Docker](https://docs.docker.com/get-docker/) and Docker Compose

That's it — everything (PHP, MySQL, Redis, Nginx) runs in containers, so you don't need PHP or a
database installed locally.

---

## Installation (Development)

### 1. Clone the repository

```bash
git clone <your-repo-url> tidewrk
cd tidewrk
```

### 2. Create the environment file

```bash
cp .env.example .env
```

Update these values so they match the Docker service names:

```dotenv
APP_URL=http://localhost:8080

# Database — MySQL container
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=tidewrk
DB_USERNAME=tidewrk
DB_PASSWORD=secret

# Queue & cache — Redis container
QUEUE_CONNECTION=redis
CACHE_STORE=redis

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

> **Two host values must point at the container names, not `127.0.0.1`:**
> - `DB_HOST=db`
> - `REDIS_HOST=redis`
>
> Inside the `app` container, `127.0.0.1` means the container itself — not the MySQL or
> Redis container. `db` and `redis` are the service names from `docker-compose.yml`.
>
> Also: don't leave `DB_PASSWORD` empty — it must match the `db` service password
> (`secret`), or the connection is refused. `REDIS_PASSWORD=null` is correct (the Redis
> container has no password).

### 3. Build and start the containers

```bash
docker compose up -d --build
```

This starts five services:

| Service | Purpose | Exposed at |
|---------|---------|------------|
| `nginx` | Web server | http://localhost:8080 |
| `app`   | PHP-FPM (Laravel) | — |
| `db`    | MySQL 8.0 | localhost:3306 |
| `redis` | Cache + queue | localhost:6379 |
| `queue` | Background import worker | — |

### 4. Install dependencies & bootstrap the app

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

### 5. Verify

Open **http://localhost:8080** — you should reach the Laravel app.

---

## Usage

### Upload a file

```bash
curl -X POST http://localhost:8080/api/uploads \
  -F "file=@storage/app/sample/students.xlsx"
```

Example response:

```json
{
  "message": "Import queued successfully.",
  "batch_id": "9b1c...",
  "status_url": "/api/uploads/9b1c.../status"
}
```

The queued `queue` worker processes the import in the background. Poll the status URL (or check
the DB) to see progress.

### Expected file format

Each row contains **both** school and student columns. School columns repeat across rows:

| school_code | school_name    | student_number | student_name | student_email      |
|-------------|----------------|----------------|--------------|--------------------|
| SCH001      | Springfield HS | STU1001        | Bart Simpson | bart@example.com   |
| SCH001      | Springfield HS | STU1002        | Lisa Simpson | lisa@example.com   |
| SCH002      | Shelbyville HS | STU2001        | Milhouse V.  | milhouse@ex.com    |

> **Natural keys**: schools are deduplicated on `school_code`, students on `student_number`.
> Adjust these in the migration + import class if your file uses different identifiers.

---

## Common Dev Commands

```bash
# View logs
docker compose logs -f app
docker compose logs -f queue

# Open a shell in the app container
docker compose exec app bash

# Run tests
docker compose exec app php artisan test

# Re-run migrations from scratch
docker compose exec app php artisan migrate:fresh

# Restart the queue worker after editing a job class
docker compose restart queue

# Stop everything
docker compose down

# Stop and wipe the database volume
docker compose down -v
```

---

## Project Structure

```
.
├── app/                    # Laravel application code
├── docker/
│   ├── php/Dockerfile      # PHP 8.3-FPM image (Laravel + Laravel Excel extensions)
│   └── nginx/default.conf  # Nginx site config (large upload limits)
├── docker-compose.yml      # app, nginx, db, redis, queue services
├── routes/api.php          # API routes
└── README.md
```

---

## Notes

- Nginx is configured for large uploads (`client_max_body_size 100M`, `fastcgi_read_timeout 300`).
- Imports run through Laravel Excel with `WithChunkReading` + `WithBatchInserts` and are dispatched
  as queued jobs (`ShouldQueue`) so the HTTP request returns immediately.
- Schools are inserted via bulk `upsert()` against a unique key, guaranteeing single insertion even
  under concurrency.
