# Catalogue API

REST API do zarządzania produktami i kategoriami, zbudowane w Symfony i API Platform,
uruchamiane jednym poleceniem `docker compose up`.

*[English version →](README.md)* · Oryginalna treść zadania znajduje się w
[`docs/task-spec.md`](docs/task-spec.md).

## Stack

| Komponent | Wersja |
|---|---|
| PHP | 8.4 |
| Symfony | 8.1 (najnowsza stabilna) |
| API Platform | 4.3 |
| Doctrine ORM | 3.7 |
| MySQL | 8.4 |
| Mailpit | najnowszy — przechwytuje wysyłaną pocztę |

## Wymagania

Docker Desktop (lub Docker Engine + Compose v2). Nic poza tym: PHP, Composer i MySQL
działają wewnątrz kontenerów, więc na hoście nie trzeba niczego instalować.

## Uruchomienie

```bash
git clone https://github.com/dusia-s/catalogue-api.git
cd catalogue-api

# 1. Zbuduj i uruchom php-fpm, nginx, MySQL i Mailpit
docker compose up -d --build

# 2. Zainstaluj zależności
docker compose exec php composer install

# 3. Utwórz schemat bazy
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

# 4. Opcjonalnie: załaduj dane przykładowe
docker compose exec php bin/console doctrine:fixtures:load --no-interaction
```

Następnie:

| Co | Gdzie |
|---|---|
| Swagger UI | <http://localhost:8080/api/docs> |
| Punkt wejścia API | <http://localhost:8080/api> |
| Mailpit (wysłane maile) | <http://localhost:8025> |

Port MySQL **celowo** nie jest publikowany — lokalnie działający MySQL na porcie 3306
to najczęstsza przyczyna tego, że świeżo sklonowany projekt się nie uruchamia. Dostęp do
bazy:

```bash
docker compose exec database mysql -uapp -papp catalogue
```

## Testy

Testy korzystają z osobnego schematu `catalogue_test`, tworzonego i nadawanego przez
[`docker/mysql/init.sql`](docker/mysql/init.sql) przy pierwszej inicjalizacji wolumenu
bazy. Wystarczy raz wykonać migrację, a potem uruchamiać PHPUnit:

```bash
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction --env=test
docker compose exec php bin/phpunit
```

Każdy test działa w transakcji wycofywanej po jego zakończeniu
(`dama/doctrine-test-bundle`), więc testy nie przenoszą stanu między sobą ani nie
naruszają danych deweloperskich. W środowisku testowym poczta używa transportu
`null://`, więc Mailpit nie musi być dostępny.

## API

### Endpointy

| Metoda | Ścieżka | Uwagi |
|---|---|---|
| `GET` | `/api/products` | kolekcja z paginacją |
| `GET` | `/api/products/{id}` | |
| `POST` | `/api/products` | |
| `PUT` | `/api/products/{id}` | pełne zastąpienie |
| `PATCH` | `/api/products/{id}` | częściowa aktualizacja |
| `DELETE` | `/api/products/{id}` | |
| `GET` | `/api/categories`, `/api/categories/{id}` | |
| `POST` | `/api/categories` | |
| `GET` | `/api/operation_logs`, `/api/operation_logs/{id}` | tylko do odczytu |

### Powiązanie produktu z kategoriami

Kategorie wskazuje się przez ich IRI w treści żądania produktu, więc osobny endpoint do
przypisywania nie jest potrzebny:

```bash
# Utwórz dwie kategorie. Kody muszą być unikalne, więc poniższe celowo nie
# występują w danych przykładowych. Każda odpowiedź zawiera "@id", do którego
# potem się odwołujemy.
curl -X POST http://localhost:8080/api/categories \
  -H 'Content-Type: application/ld+json' \
  -d '{"code":"SADDLES"}'
# → {"@id":"/api/categories/9","code":"SADDLES",...}

curl -X POST http://localhost:8080/api/categories \
  -H 'Content-Type: application/ld+json' \
  -d '{"code":"TOURING"}'
# → {"@id":"/api/categories/10","code":"TOURING",...}

# Utwórz produkt należący do obu, używając dwóch powyższych wartości "@id"
curl -X POST http://localhost:8080/api/products \
  -H 'Content-Type: application/ld+json' \
  -d '{
        "name": "Leather Touring Saddle",
        "price": "459.00",
        "categories": ["/api/categories/9", "/api/categories/10"]
      }'
```

> Powyższe identyfikatory są poglądowe. Ładowanie fixtures usuwa wiersze przez
> `DELETE`, co nie resetuje `AUTO_INCREMENT` w MySQL, więc po ponownym załadowaniu
> identyfikatory będą inne — należy używać wartości `@id` zwróconych przez własne
> odpowiedzi.

Zmiana kategorii produktu to `PATCH` z samą nową listą:

```bash
curl -X PATCH http://localhost:8080/api/products/1 \
  -H 'Content-Type: application/merge-patch+json' \
  -d '{"categories":["/api/categories/1"]}'
```

> **`PATCH` wymaga nagłówka `Content-Type: application/merge-patch+json`.** Zwykłe
> `application/json` kończy się odpowiedzią `415 Unsupported Media Type`. Dostępne jest
> również `PUT`, które przyjmuje `application/ld+json`, jeśli wygodniejsze jest pełne
> zastąpienie zasobu.

### Walidacja

| Reguła | Odpowiedź |
|---|---|
| Kod kategorii musi być unikalny (porównanie bez uwzględniania wielkości liter, więc `BIKES` i `bikes` kolidują) | `422` |
| Kod kategorii maksymalnie 10 znaków | `422` |
| Kod kategorii może zawierać tylko litery, cyfry, `-` i `_` | `422` |
| Nazwa produktu wymagana i nie może być samymi spacjami | `422` |
| Cena produktu: do 8 cyfr i 2 miejsc po przecinku, nieujemna | `422` |
| Produkt musi mieć co najmniej jedną kategorię | `422` |
| IRI kategorii, które nie istnieje | `400` |

Błędy zwracane są jako dokumenty problem+json (RFC 7807).

Wzorzec ceny odzwierciedla dokładnie kolumnę `DECIMAL(10,2)`. Pozostawienie formatu
bazie danych oznacza, że `"abc"` i zbyt duża wartość przechodzą walidację i wywracają
się dopiero na `INSERT`, zamieniając błąd klienta w 500, a trzecie miejsce po przecinku
jest po cichu zaokrąglane. Zapięcie tego w walidacji sprawia, że wszystkie trzy
przypadki kończą się kodem 422.

## Decyzje projektowe

Cztery rzeczy, które mogą wymagać wyjaśnienia.

### Ceny są tekstem

`price` to kolumna `DECIMAL(10,2)` i w JSON-ie pojawia się jako `"price": "89.50"`, a nie
jako liczba. Liczby zmiennoprzecinkowe nie reprezentują dokładnie każdej wartości z dwoma
miejscami po przecinku, a Doctrine mapuje `DECIMAL` na string, żeby zachować precyzję na
całej drodze. Sparsowanie tekstu do typu dziesiętnego po stronie klienta jest
bezpieczniejsze niż zgoda na zaokrąglenia.

### Znaczniki czasu działają też przy zmianie samych relacji

`created_at` i `updated_at` ustawiają lifecycle callbacks w
[`TimestampableTrait`](src/Doctrine/Behavior/TimestampableTrait.php). Doctrine wyznacza
jednak zestaw zmian na podstawie własnych pól skalarnych encji, więc zmiana *wyłącznie*
kategorii produktu zostawia go „czystym” i `PreUpdate` nigdy się nie wykonuje — data
aktualizacji byłaby nieaktualna dokładnie przy operacji, którą zadanie wymienia wprost.
[`TimestampableCollectionListener`](src/Doctrine/TimestampableCollectionListener.php)
domyka tę lukę w `onFlush`. Działa na warstwie persystencji, a nie w warstwie API, więc
ta sama gwarancja obowiązuje dla fixtures, komend i dowolnego przyszłego kodu.

### Kategorii nie można usuwać

Zadanie ogranicza operacje zapisu do produktów; kategorie muszą jedynie istnieć, żeby
produkty mogły się do nich odwoływać. Reguła „produkt ma co najmniej jedną kategorię”
jest pilnowana przez walidację, którą kaskadowe usunięcie kategorii by ominęło,
zostawiając produkty bez przypisania. Dodanie operacji usuwania wymagałoby najpierw
rozstrzygnięcia, co dzieje się z takimi produktami, więc zamiast zostawiać ją w
niebezpiecznej postaci, została pominięta.

### Powiadomienia nie wycofują zapisu

Wysyłka następuje po zatwierdzeniu produktu i powiązań z kategoriami. Awaria kanału nie
może więc cofnąć zapisu, a dispatcher izoluje kanały, żeby odbity mail nadal zostawił
wpis w logu operacji. To świadomy kompromis: zapis jest tym, o co prosił klient, a
powiadomienie jest jego konsekwencją.

## Powiadomienia

Zapis produktu tworzy powiadomienie dostarczane do każdego kanału, który je obsługuje.
Obecnie są dwa:

- **[`LogNotificationChannel`](src/Notification/Channel/LogNotificationChannel.php)** —
  zapisuje wiersz `operation_log` oraz linię w kanale Monologa `notification` (widoczną w
  `docker compose logs php`). Do odczytu pod `/api/operation_logs`.
- **[`EmailNotificationChannel`](src/Notification/Channel/EmailNotificationChannel.php)** —
  wysyła e-mail, przechwytywany przez Mailpit na <http://localhost:8025>.

### Jak to działa

```
POST /api/products
  └─ ProductPersistProcessor      zapisuje przez API Platform, następnie wysyła…
       └─ ProductSavedNotification (Messenger, transport sync)
            └─ ProductSavedNotificationHandler   buduje obiekt Notification
                 └─ NotificationDispatcher       rozsyła do kanałów, które go supports()
                      ├─ LogNotificationChannel
                      └─ EmailNotificationChannel
```

### Dodanie kanału — Slack, SMS, cokolwiek

Wystarczy jedna klasa. To cała zmiana:

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

`NotificationChannelInterface` ma atrybut `#[AutoconfigureTag]`, więc nowy serwis zostaje
otagowany automatycznie, a
[`NotificationDispatcher`](src/Notification/NotificationDispatcher.php) odbiera go przez
tagged iterator. Nie trzeba rejestrować tagu, pisać definicji serwisu ani zmieniać
żadnego istniejącego pliku.

`supports()` utrzymuje niezależność kanałów: kanał logu przyjmuje wszystko, bo jest
dziennikiem operacji, a e-mail tylko `product.saved`, więc przyszłe powiadomienie
wewnętrzne nie wyśle domyślnie maila do nikogo.

### Przełączenie wysyłki na asynchroniczną

Powiadomienia korzystają z transportu `sync`, więc stack nie potrzebuje workera.
Przeniesienie ich poza żądanie to zmiana routingu w
[`config/packages/messenger.yaml`](config/packages/messenger.yaml):

```yaml
transports:
    async: '%env(MESSENGER_TRANSPORT_DSN)%'
routing:
    'App\Message\ProductSavedNotification': async
```

a następnie `bin/console messenger:consume async`. `ProductSavedNotification` celowo
przenosi płaski zestaw danych produktu zamiast encji — właśnie po to, żeby przetrwał
serializację, gdy ten dzień nadejdzie.

## Bezpieczeństwo

**Nie ma uwierzytelniania.** Zadanie go nie wymaga, więc wszystkie endpointy są otwarte.
To świadoma decyzja o zakresie, a nie przeoczenie: dodanie autoryzacji oznaczałoby
wymyślenie modelu użytkownika i przekazywanie recenzentowi danych logowania.
`SecurityBundle` nie jest zainstalowany — to pierwszy krok, jeśli projekt miałby kiedyś
opuścić zaufaną sieć.

Co jest zabezpieczone:

- **Mass assignment** — grupy serializacji trzymają `id`, `createdAt` i `updatedAt` poza
  kontekstem zapisu, więc żądanie ustawiające te pola jest ignorowane.
- **Rozmiar strony** — klient nie może przekazać `itemsPerPage`; decyduje serwer, więc
  nie da się jednym żądaniem pobrać całej tabeli.
- **SQL injection** — wszystkie zapytania idą przez Doctrine z parametrami wiązanymi.
- **Format danych** — walidacja określa kształt każdego pola zamiast zostawiać to
  kolumnie, więc błędne dane kończą się kodem 422, a nie wyjątkiem ze sterownika.
- **Nagłówki** — `X-Content-Type-Options: nosniff` i `X-Frame-Options: deny` w każdej
  odpowiedzi.

### Uruchomienie poza środowiskiem deweloperskim

Projekt startuje z `APP_ENV=dev`, co jest wygodne przy przeglądaniu. Dwie rzeczy zmieniają
się przy prawdziwym wdrożeniu:

- **Szczegóły błędów.** W `dev` odpowiedź błędu zawiera `trace` z bezwzględnymi ścieżkami
  plików; w `prod` już nie. Należy ustawić `APP_ENV=prod`.
- **Sekrety.** `APP_SECRET` w `.env` to wartość deweloperska, a dane dostępowe do MySQL
  (`app:app` oraz `root` kontenera) dotyczą wyłącznie lokalnego stacka. Jedno i drugie
  trzeba nadpisać przez `.env.local` lub zmienne środowiskowe.

## Konfiguracja

Wartości domyślne znajdują się w wersjonowanych plikach `.env`; nadpisania specyficzne
dla maszyny należy umieszczać w `.env.local` (poza repozytorium).

| Zmienna | Domyślnie | Znaczenie |
|---|---|---|
| `DATABASE_URL` | `mysql://app:app@database:3306/catalogue` | `database` to nazwa serwisu w compose |
| `MAILER_DSN` | `smtp://mailpit:1025` | `null://null` w testach |
| `NOTIFICATION_EMAIL_FROM` | `no-reply@catalogue-api.local` | nadawca powiadomienia |
| `NOTIFICATION_EMAIL_TO` | `catalogue-team@catalogue-api.local` | odbiorca powiadomienia |

## Struktura projektu

```
docker/                        Dockerfile, konfiguracja nginx, skrypt init MySQL
src/
  Doctrine/                    obsługa znaczników czasu + listener onFlush
  Entity/                      Product, Category, OperationLog
  Message/, MessageHandler/    komunikat o zapisie produktu i jego handler
  Notification/                dispatcher, kontrakt kanału, kanały
  State/                       ProductPersistProcessor
  DataFixtures/                dane przykładowe
tests/                         odzwierciedla src/, skupione na ścieżce powiadomień
migrations/                    historia zmian schematu
```
