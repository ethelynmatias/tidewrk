
## Question and Answers
**1. Event Driven and Robust? Yes, structurally**

**2. Why each state exists**

- `pending` — order created, nothing may run yet
- `confirmed` — customer committed; fulfillment starts
- `partially_failed` — something failed after retries; the order is visibly "stuck", not silently broken
- `completed` — everything succeeded (final)
- `cancelled` — deliberately stopped (final)

**3. Allowed transitions (everything else is forbidden)**

```
pending          → confirmed | cancelled
confirmed        → completed | partially_failed | cancelled
partially_failed → completed | cancelled
completed        → (final)
cancelled        → (final)
```

Notably forbidden: skipping fulfillment (`pending → completed`), re-confirming a failed order (retries happen per step), and leaving a final state.

**4. Domain-level prevention**

Only one method can change status: `Order::transitionTo()`. It checks the transition map and throws if the move is illegal — no matter who calls it (controller, job, tinker). The database backs it up: the update only applies if the row still has the status and version we read, so two workers can't overwrite each other. This is domain enforcement, not form validation.

**5. You may use queues, but must explain which parts are queued vs synchronous and why**

**Synchronous:** the `pending → confirmed` transition — the customer waits for this answer, and it's just one fast database write.

**Queued:** the three listeners (inventory, payment, shipping) and the compensation job — they call slow external systems, so they must not block the customer, and the queue gives each one its own retries. One failing never stops the others.

**Rule of thumb:** what the customer waits for is synchronous; side effects on external systems are queued.

**6. You must demonstrate awareness of idempotency**

We applied idempotency for the ff files, each covering a different way a duplicate can happen:

- database/migrations/2026_07_22_110001_create_order_steps_table.php
- app/Domain/Order/Models/OrderStep.php 
- app/Domain/Order/Enums/StepState.php 
- ReserveInventory.php, CapturePayment.php, BookShipment.php
- app/Domain/Order/Jobs/ReleaseInventoryCompensation.php


**7. You must show how to safely retry failed listeners without duplicating side effects**

Applied for the ff files 

- CapturePayment.php 
- PaymentGateway.php 

**8. Why is this order stuck?**

Look at the order's status first: partially_failed means something gave up. Then the step rows show exactly where — e.g. inventory: succeeded, payment: failed, shipping: succeeded. A row sitting in running for a long time = a hung worker.

**9. Which step failed?**

The row with status = 'failed' — and the actual error is saved right next to it:
error_class = RuntimeException, error = "Payment declined by gateway". No log digging.

**10. Was this failure retried?**

Read the attempt column. Every re-run after a failure increments it (markRunning() in OrderStep.php). attempt = 3 = all retries used up.

**11. Is this safe to retry again?**

Yes if status is not succeeded / skipped / compensated. And even if you retry by accident, nothing duplicates — the idempotency_key makes a re-run find the existing work instead of doing it twice.

**12. What would break first at 10× scale, and how I would address it**

- The ledger grows forever. 3+ rows per order adds up.
Fix: archive rows of completed/cancelled orders after a retention period; only live orders need to be hot.
- One queue for everything. A slow shipping API blocks payment jobs waiting behind it.
Fix: separate queues per step (payments, shipping, …) with their own workers. One slow service only delays itself.


## Tech Stack

| Component | Version |
|-----------|---------|
| PHP       | 8.4 (FPM) |
| Laravel   | Latest |
| Database  | MySQL 8.4 |
| Cache/Queue | Redis |
| Web server | Nginx |
| CSV parsing | Native PHP streaming (`SplFileObject`) |

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

# PART 3: DDD

An event-driven order fulfillment workflow inside the Laravel monolith — no
microservices, no queues outside Laravel. Confirming an order fans out one domain event
to independent, queued, idempotent listeners (inventory, payment, shipping); every step
records its outcome in an execution ledger; unrecoverable failures trigger explicit
compensation.

## Automated Tests

```bash
php artisan test tests/Feature/Domain/Order              # local PHP
# or inside Docker:
docker compose exec app php artisan test tests/Feature/Domain/Order
# or the whole suite:
make test
```

Expected: **13 passed** across 3 suites. Tests run on sqlite in-memory with the `sync`
queue driver — no containers or worker needed. Every scenario from the brief maps to a
named test:

| Scenario | Test |
|----------|------|
| Legal transitions (pending→confirmed, recovery to completed) | `OrderLifecycleTest::test_pending_order_can_be_confirmed`, `…partially_failed_can_recover_to_completed` |
| Illegal transitions blocked at the domain level | `OrderLifecycleTest::test_pending_order_cannot_skip_to_completed`, `…terminal_states_cannot_transition_anywhere`, `…partially_failed_cannot_be_reconfirmed` |
| Happy path: confirm → fan-out → all steps succeed → completed | `FulfillmentFlowTest::test_confirming_an_order_fans_out_and_completes_it` |
| Same event dispatched twice → no duplicate side effects | `FulfillmentFlowTest::test_duplicate_event_dispatch_causes_no_duplicate_side_effects` |
| Payment fails after inventory reserved → compensation releases stock | `FulfillmentFlowTest::test_payment_failure_compensates_the_inventory_reservation` |
| Compensation accidentally runs twice → exactly one release | `FulfillmentFlowTest::test_compensation_is_idempotent` |
| Failed step retried → succeeds, `attempt` counted, no re-run of other steps | `FulfillmentFlowTest::test_failed_step_can_be_retried_without_duplicating_side_effects` |
| Two workers transition the same order → loser throws, never clobbers | `ConcurrencyTest::test_concurrent_workers_cannot_both_transition_the_same_order` |
| Duplicate step claim → one ledger row | `ConcurrencyTest::test_duplicate_step_claims_share_one_ledger_row` |
| Queued event arrives after cancellation → steps skipped, zero side effects | `ConcurrencyTest::test_queued_event_arriving_after_cancellation_is_skipped` |

Partial failures are made deterministic by the fake services' failure switches
(set in `.env` for manual poking, or `config([...])` inside a test):

```dotenv
FULFILLMENT_FAIL_INVENTORY=true   # simulated stock-out
FULFILLMENT_FAIL_PAYMENT=true     # simulated gateway decline
FULFILLMENT_FAIL_SHIPPING=true    # simulated carrier timeout
```

> **Sync-driver nuance** (documented in the tests): with `QUEUE_CONNECTION=sync`, a
> throwing listener fires its `failed()` hook immediately, so the whole recovery flow
> runs inline in one call. In production with Redis, each listener is an isolated
> queued job with real retries (`tries = 3`, backoff `10s/60s/300s`).

### Manual testing with a real queue

```bash
make queue                             
docker compose exec app php artisan tinker   
```

```php
$order = App\Domain\Order\Models\Order::create(['order_number' => 'ORD-100', 'total_amount' => 250]);
app(App\Domain\Order\Services\OrderFulfillmentService::class)->confirm($order);
// watch terminal 1 process the three listener jobs, then:
$order->refresh()->status;                                    
$order->steps->map->only('step', 'status', 'attempt', 'payload');
```

Set `FULFILLMENT_FAIL_PAYMENT=true` in `.env` and restart the worker to watch a real
retry cycle: payment fails 3× with backoff, `failed()` fires, the order goes
`partially_failed`, and the compensation job releases the reservation. Then inspect the
ledger exactly the way an on-call engineer would:

```sql
SELECT step, status, attempt, error_class, error, started_at, finished_at
FROM order_steps WHERE order_id = ?;
```

## File Structure

Everything related to the Order domain is grouped under `app/Domain/Order` — for a
complex business workflow like fulfillment, cohesion by domain beats Laravel's default
technical-layer folders.

```
app/
├── Domain/
│   └── Order/
│       ├── Models/
│       │   ├── Order.php                        # Domain-level transition guard + optimistic locking
│       │   └── OrderStep.php                    # Per-step execution ledger (observability core)
│       ├── Events/
│       │   └── OrderConfirmed.php               # Single domain event fanned out to listeners
│       ├── Listeners/
│       │   ├── ReserveInventory.php             # Queued, idempotent
│       │   ├── CapturePayment.php               # Queued, idempotent
│       │   └── BookShipment.php                 # Queued, idempotent
│       ├── Jobs/
│       │   └── ReleaseInventoryCompensation.php # Compensating action (payment failed after reserve)
│       ├── Services/
│       │   ├── OrderFulfillmentService.php      # Confirms order, evaluates step outcomes
│       │   ├── PaymentGateway.php               # Fake gateway with configurable failure switch
│       │   └── InventoryService.php             # Fake inventory with reserve/release
│       ├── Enums/
│       │   └── StepState.php                    # Ledger states: pending|running|succeeded|failed|skipped|compensated
│       ├── Exceptions/
│       │   ├── InvalidOrderTransitionException.php
│       │   └── StaleOrderException.php
│       └── State/
│           └── OrderStatus.php                  # The state machine: states + allowed transitions
├── Http/
│   └── Controllers/
│       └── Controller.php                       # Base controller only — no order endpoints on purpose:
│                                                #   the brief needs no UI; the workflow is driven via the
│                                                #   domain service (tests/tinker), not HTTP
├── Models/
│   └── User.php                                 # Pre-existing monolith model (auth)
└── Providers/
    └── AppServiceProvider.php                   # Event→listener wiring + fake-service singletons

config/
└── fulfillment.php                              # FULFILLMENT_FAIL_* failure switches


database/migrations/
├── 0001_01_01_*                                 # Laravel defaults (users, cache, jobs/failed_jobs)
├── 2026_07_22_110000_create_orders_table.php    # status, version (optimistic lock), lifecycle timestamps
└── 2026_07_22_110001_create_order_steps_table.php   # The ledger — the key domain decision

tests/Feature/
├── Domain/Order/
│   ├── OrderLifecycleTest.php                   # Transition rules, terminal states
│   ├── FulfillmentFlowTest.php                  # Fan-out, idempotency, failure + compensation
│   └── ConcurrencyTest.php                      # Races, duplicate claims, post-cancel events
└── ExampleTest.php
```

## Event Flow

```
POST-like entrypoint (service call — no HTTP by design)
        │
        ▼
OrderFulfillmentService::confirm($order)
        │
        ├─ Order::transitionTo(Confirmed)          SYNC — domain guard + optimistic lock;
        │                                          the customer gets an immediate, truthful answer
        │
        └─ OrderConfirmed::dispatch($order->id)    fired only AFTER the transition committed
                    │
                    │  fan-out: three INDEPENDENT queued listeners
                    │  (tries = 3, backoff 10s/60s/300s, each idempotent via the ledger)
        ┌───────────┼───────────────┐
        ▼           ▼               ▼
ReserveInventory  CapturePayment  BookShipment
        │           │               │
        │  each listener, same shape:
        │    1. re-read the order (event carries only the id)
        │    2. OrderStep::claim() — firstOrCreate on unique idempotency_key
        │    3. final state already? → no-op        (duplicate dispatch is harmless)
        │    4. order cancelled?    → markSkipped   (records WHY nothing happened)
        │    5. markRunning → call external system with the idempotency key
        │    6. markSucceeded(external_ref) │ markFailed(exception) + rethrow for retry
        │           │               │
        └───────────┴───────┬───────┘
                            ▼
        evaluateOutcome(): all 3 steps succeeded?
                            │
                            ▼
        Order::transitionTo(Completed)     ← optimistic lock resolves the race
                                             on the last two finishing steps
```

### Failure path (implemented scenario: payment declines after inventory reserved)

```
ReserveInventory ──────────── succeeded (stock held, reservation ref in ledger)
CapturePayment  ── attempt 1 ── failed (recorded: error_class + message)
                ── attempt 2 ── failed        │ queue retries with backoff
                ── attempt 3 ── failed        ▼
                        failed() hook — retries exhausted
                                │
                                ▼
        OrderFulfillmentService::handleStepExhausted($orderId, 'payment')
                                │
                ├─ Order::transitionTo(PartiallyFailed)     ← order is queryably "stuck",
                │                                             not silently broken
                └─ ReleaseInventoryCompensation::dispatch()  queued compensating job
                                │
                                ▼
                inventory step still 'succeeded'?  ── no ──▶ no-op (idempotent:
                                │ yes                        double-run releases nothing twice)
                                ▼
                InventoryService::release(reservation_ref)
                                │
                                ▼
                step → 'compensated'   (stock freed for other customers)
```

### Cancellation race (queued event arrives after the order was cancelled)

```
Order cancelled ──▶ OrderConfirmed finally delivered ──▶ each listener re-reads the order,
                                                         sees Cancelled, marks its step
                                                         'skipped' — zero side effects,
                                                         and the ledger records why
```
