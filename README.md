# Catalogue API

A REST API for managing products and categories, built with Symfony and API Platform
and packaged so it starts with a single `docker compose up`.

*[Wersja polska →](README.pl.md)* · The original task brief is in
[`docs/task-spec.md`](docs/task-spec.md).

## Stack

| Component | Version |
|---|---|
| PHP | 8.4 |
| Symfony | 8.1 (latest stable) |
| API Platform | 4.3 |
| Doctrine ORM | 3.7 |
| MySQL | 8.4 |
| Mailpit | latest — captures outgoing mail |

## Requirements

Docker Desktop (or Docker Engine + Compose v2). Nothing else: PHP, Composer and MySQL
all run inside the stack, so there is nothing to install on the host.

## Getting started

```bash
git clone https://github.com/dusia-s/catalogue-api.git
cd catalogue-api

# 1. Build and start php-fpm, nginx, MySQL and Mailpit
docker compose up -d --build

# 2. Install dependencies
docker compose exec php composer install

# 3. Create the schema
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

# 4. Optional: load seed data so the API is not empty
docker compose exec php bin/console doctrine:fixtures:load --no-interaction
```

Then open:

| What | Where |
|---|---|
| Swagger UI | <http://localhost:8080/api/docs> |
| API entrypoint | <http://localhost:8080/api> |
| Mailpit (sent mail) | <http://localhost:8025> |

The MySQL port is deliberately **not** published — a MySQL already listening on the
host's 3306 is the usual reason a fresh clone fails to start. Reach the database with:

```bash
docker compose exec database mysql -uapp -papp catalogue
```

## Running the tests

The test suite uses a separate `catalogue_test` schema, created and granted by
[`docker/mysql/init.sql`](docker/mysql/init.sql) when the database volume is first
initialised. Migrate it once, then run PHPUnit:

```bash
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction --env=test
docker compose exec php bin/phpunit
```

Each test runs inside a transaction that is rolled back afterwards
(`dama/doctrine-test-bundle`), so the tests neither leak state into one another nor
touch your development data. Mail uses the `null://` transport under test, so Mailpit
does not need to be reachable.

## API

### Endpoints

| Method | Path | Notes |
|---|---|---|
| `GET` | `/api/products` | paginated collection |
| `GET` | `/api/products/{id}` | |
| `POST` | `/api/products` | |
| `PUT` | `/api/products/{id}` | full replacement |
| `PATCH` | `/api/products/{id}` | partial update |
| `DELETE` | `/api/products/{id}` | |
| `GET` | `/api/categories`, `/api/categories/{id}` | |
| `POST` | `/api/categories` | |
| `GET` | `/api/operation_logs`, `/api/operation_logs/{id}` | read-only |

### Linking a product to categories

Categories are referenced by their IRI in the product payload, so no separate
association endpoint is needed:

```bash
# Create two categories. Codes must be unique, so these are ones the seed
# fixtures do not use. Each response carries the "@id" you then reference.
curl -X POST http://localhost:8080/api/categories \
  -H 'Content-Type: application/ld+json' \
  -d '{"code":"SADDLES"}'
# → {"@id":"/api/categories/9","code":"SADDLES",...}

curl -X POST http://localhost:8080/api/categories \
  -H 'Content-Type: application/ld+json' \
  -d '{"code":"TOURING"}'
# → {"@id":"/api/categories/10","code":"TOURING",...}

# Create a product belonging to both, using the two "@id" values above
curl -X POST http://localhost:8080/api/products \
  -H 'Content-Type: application/ld+json' \
  -d '{
        "name": "Leather Touring Saddle",
        "price": "459.00",
        "categories": ["/api/categories/9", "/api/categories/10"]
      }'
```

> The ids above are illustrative. Loading the fixtures purges rows with `DELETE`,
> which leaves MySQL's `AUTO_INCREMENT` where it was, so your own ids will differ
> after a reload — always use the `@id` values your responses actually return.

Re-categorising a product is a `PATCH` with just the new list:

```bash
curl -X PATCH http://localhost:8080/api/products/1 \
  -H 'Content-Type: application/merge-patch+json' \
  -d '{"categories":["/api/categories/1"]}'
```

> **`PATCH` requires `Content-Type: application/merge-patch+json`.** A plain
> `application/json` body is answered with `415 Unsupported Media Type`. `PUT` is also
> enabled and does accept `application/ld+json`, if full replacement suits you better.

### Validation

| Rule | Response |
|---|---|
| Category `code` must be unique (the collation is case-insensitive, so `BIKES` and `bikes` collide) | `422` |
| Category `code` at most 10 characters | `422` |
| Category `code` may contain only letters, digits, `-` and `_` | `422` |
| Product `name` required, and not only whitespace | `422` |
| Product `price` up to 8 digits and 2 decimals, non-negative | `422` |
| Product must have at least one category | `422` |
| Category IRI that does not resolve | `400` |

Errors come back as RFC 7807 problem documents.

The price pattern mirrors the `DECIMAL(10,2)` column exactly. Leaving the format to the
database means `"abc"` and an over-long value pass validation and fail on `INSERT`,
turning a client mistake into a 500, while a third decimal place is quietly rounded
away. Pinning it in the constraint keeps all three answers a 422.

## Design notes

Four decisions a reader might otherwise wonder about.

### Prices are strings

`price` is a `DECIMAL(10,2)` column and appears in JSON as `"price": "89.50"`, not as a
number. Binary floats cannot represent every two-decimal value exactly, and Doctrine
maps `DECIMAL` to a PHP string to preserve that precision end to end. Parsing the
string into whatever decimal type the client uses is safer than accepting rounding.

### Timestamps survive relation-only changes

`created_at` and `updated_at` are maintained by lifecycle callbacks in
[`TimestampableTrait`](src/Doctrine/Behavior/TimestampableTrait.php). Doctrine, however,
computes a change set from an entity's own scalar fields, so changing *only* a product's
categories leaves it "clean" and `PreUpdate` never fires — the timestamp would go stale
on exactly the operation the brief highlights.
[`TimestampableCollectionListener`](src/Doctrine/TimestampableCollectionListener.php)
closes that gap in `onFlush`. It lives at the persistence layer rather than in the API
layer, so fixtures, commands and any future caller get the same guarantee.

### Categories cannot be deleted

The brief scopes create/update/delete to products; categories only need to exist so
products can reference them. "A product keeps at least one category" is enforced by
validation, which a cascading category delete would bypass and leave orphaned products
behind. Adding a delete operation would mean deciding what happens to those products
first, so the operation is omitted rather than left unsafe.

### Notifications cannot roll back a save

Delivery happens after the product and its category links are committed. A failing
channel therefore cannot undo the save, and the dispatcher isolates each channel so a
bounced e-mail still leaves the operation log written. The trade-off is deliberate: the
write is the thing the client asked for, and the notification is a consequence of it.

## Notifications

Saving a product raises a notification that is delivered to every channel that accepts
it. Two ship today:

- **[`LogNotificationChannel`](src/Notification/Channel/LogNotificationChannel.php)** —
  writes an `operation_log` row and a line on the `notification` Monolog channel
  (visible in `docker compose logs php`). Readable at `/api/operation_logs`.
- **[`EmailNotificationChannel`](src/Notification/Channel/EmailNotificationChannel.php)** —
  sends an e-mail, captured by Mailpit at <http://localhost:8025>.

### How it fits together

```
POST /api/products
  └─ ProductPersistProcessor      persists via API Platform, then dispatches…
       └─ ProductSavedNotification (Messenger, sync transport)
            └─ ProductSavedNotificationHandler   builds a Notification
                 └─ NotificationDispatcher       fans out to every channel that supports() it
                      ├─ LogNotificationChannel
                      └─ EmailNotificationChannel
```

### Adding a channel — Slack, SMS, anything

Write one class. That is the whole change:

```php
final class SlackNotificationChannel implements NotificationChannelInterface
{
    public function __construct(private HttpClientInterface $http) {}

    public function supports(Notification $notification): bool
    {
        return NotificationType::ProductSaved === $notification->type;
    }

    public function send(Notification $notification): void
    {
        $this->http->request('POST', $webhookUrl, ['json' => ['text' => $notification->message]]);
    }
}
```

`NotificationChannelInterface` carries `#[AutoconfigureTag]`, so the new service is
tagged automatically and
[`NotificationDispatcher`](src/Notification/NotificationDispatcher.php) picks it up
through its tagged iterator. No tag to register, no service definition to write, no
existing file to touch.

`supports()` is what keeps channels independent: the log channel accepts everything
because it is the audit trail, while e-mail accepts only `product.saved`, so a future
internal-only notification will not mail anyone by default.

### Making delivery asynchronous

Notifications currently run on Messenger's `sync` transport, so the stack needs no
worker. Moving them off the request is a routing change in
[`config/packages/messenger.yaml`](config/packages/messenger.yaml):

```yaml
transports:
    async: '%env(MESSENGER_TRANSPORT_DSN)%'
routing:
    'App\Message\ProductSavedNotification': async
```

then run `bin/console messenger:consume async`. `ProductSavedNotification` deliberately
carries a flat snapshot of the product rather than the entity, precisely so it survives
serialisation when that day comes.

## Configuration

Defaults live in committed `.env` files; put machine-specific overrides in `.env.local`
(git-ignored).

| Variable | Default | Purpose |
|---|---|---|
| `DATABASE_URL` | `mysql://app:app@database:3306/catalogue` | `database` is the compose service name |
| `MAILER_DSN` | `smtp://mailpit:1025` | `null://null` under test |
| `NOTIFICATION_EMAIL_FROM` | `no-reply@catalogue-api.local` | notification sender |
| `NOTIFICATION_EMAIL_TO` | `catalogue-team@catalogue-api.local` | notification recipient |

## Project layout

```
docker/                        Dockerfile, nginx vhost, MySQL init script
src/
  Doctrine/                    timestamp behaviour + the onFlush listener
  Entity/                      Product, Category, OperationLog
  Message/, MessageHandler/    the saved-product message and its handler
  Notification/                dispatcher, channel contract, channels
  State/                       ProductPersistProcessor
  DataFixtures/                seed data
tests/                         mirrors src/, concentrated on the notification path
migrations/                    schema history
```
