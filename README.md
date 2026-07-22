## Tech Stack

| Component | Version |
|-----------|---------|
| PHP       | 8.4 (FPM) |
| Laravel   | Latest |
| Database  | MySQL 8.4 |
| Cache/Queue | Redis |
| Web server | Nginx |
| Excel     | maatwebsite/excel |

---

## Requirements

- [Docker](https://docs.docker.com/get-docker/) and Docker Compose

(PHP, MySQL, Redis, Nginx) runs in containers, so you don't need PHP or a
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
APP_URL=http://localhost:8000

# Database — MySQL container
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=student_import
DB_USERNAME=laravel
DB_PASSWORD=password

# Queue & cache — Redis container
QUEUE_CONNECTION=redis
CACHE_STORE=redis

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

> **Two host values must point at the container names, not `127.0.0.1`:**
> - `DB_HOST=mysql`
> - `REDIS_HOST=redis`
>
> Inside the `app` container, `127.0.0.1` means the container itself — not the MySQL or
> Redis container. `mysql` and `redis` are the service names from `docker-compose.yml`.
>
> Also: don't leave `DB_PASSWORD` empty — it must match the `mysql` service password
> (`password`), or the connection is refused. `REDIS_PASSWORD=null` is correct (the Redis
> container has no password).

### 3. Build and start the containers

> **Shortcut (recommended):** if you have `make` installed, skip steps 3–4 and run a
> single command:
> ```bash
> make setup
> ```
> This builds the images, starts the containers (waiting until MySQL is healthy),
> installs dependencies, generates the app key, and runs migrations. Then jump to
> step 5. To do it manually, continue below.

```bash
docker compose up -d --build --wait
# or with make:
make build && make up
```

The `--wait` flag blocks until every container reports **healthy** — in particular it
waits for MySQL to finish its first-time initialization before continuing, which avoids
the "Connection refused" error that happens if you migrate too early.

This starts four services:

| Service | Purpose | Exposed at |
|---------|---------|------------|
| `nginx` | Web server | http://localhost:8000 |
| `app`   | PHP-FPM (Laravel) | — |
| `mysql` | MySQL 8.4 (with healthcheck) | localhost:3306 |
| `redis` | Cache + queue | localhost:6379 |

> The `app` container depends on MySQL's healthcheck (`condition: service_healthy`),
> so it will not start until the database is actually accepting connections.

> **Queued imports:** there's no dedicated worker container, so run the queue worker
> yourself when processing uploads:
> ```bash
> docker compose exec app php artisan queue:work
> ```

### 4. Install dependencies & bootstrap the app

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Or with make:

```bash
make install
docker compose exec app php artisan key:generate
make migrate
```

### 5. Verify

Open **http://localhost:8000** — you should reach the Laravel app.

---

## Makefile Commands

Common tasks are wrapped in a `Makefile`. Run `make` (or `make help`) to list them.

| Command | Description |
|---------|-------------|
| `make setup` | Full first-time setup (build, up, install, key, migrate) |
| `make build` | Build the Docker images |
| `make up` | Start all containers in the background |
| `make down` | Stop and remove containers (keeps DB data) |
| `make clean` | Stop and remove containers **and volumes** (wipes DB data) |
| `make restart` | Restart all containers |
| `make install` | `composer install` inside the app container |
| `make migrate` | Run database migrations |
| `make fresh` | Drop all tables and re-run migrations |
| `make queue` | Start the queue worker (processes uploads) |
| `make token` | Create a test user and print a Sanctum API token |
| `make test` | Run the test suite |
| `make shell` | Open a bash shell in the app container |
| `make logs` | Tail logs from all containers |
| `make ps` | List running containers |

---

## PART 1 (CODING) : API Authentication

The API is protected by **Laravel Sanctum**, so requests must include a Bearer token.

Generate a token (creates a test user `test@test.com` on first run):

```bash
make token
```

It prints something like:

```
User: test@test.com
Token: 1|1TYJeWv6ciMP4IMXVF9dbFn8BPR5sJdMM7VU44qXd0047b5d
```

Use it in requests via the `Authorization` header:

```bash
curl -X POST http://localhost:8000/api/v1/upload \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>" \
  -F "file=@students.xlsx"
```

In **Postman**: Authorization tab → Type **Bearer Token** → paste the token.

> Under the hood `make token` runs `php artisan api:token`. You can pass a custom email
> or label directly:
> ```bash
> docker compose exec app php artisan api:token admin@site.com --name=mobile
> ```

---

## Project Structure

```
.
├── app/                    # Laravel application code
├── docker/
│   ├── php/Dockerfile      # PHP 8.4-FPM image (Laravel + Laravel Excel extensions)
│   └── nginx/default.conf  # Nginx site config (large upload limits)
├── docker-compose.yml      # app, nginx, mysql, redis services
├── routes/api.php          # API routes
└── README.md
```
