## Како да го подигнеш проектот локално:

1. `composer install`
2. креирање на `.env` и `.env.testing`
3. `php artisan key:generate`
4. `php artisan migrate`
5. `php artisan db:seed`
6. `php artisan test`
7. `php artisan serve`

## Зошто ни требаат .env и .env.testing

Бидејќи користиме 2 databases 1 каде ги чуваме "вистинските податоци",
а другата ја користиме за тестирање затоа ќе треба да направите 2 databases во mysql

Имаме dataseeder за популација на 1 база на податоци заради потреба од мануелно тестирање

Инаку имамe веќе неколку тестови кои се однесуваат на 2та база на податоци.

## Како треба да изгледа .env.testing

```env
APP_ENV=testing
APP_KEY=

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=root
DB_PASSWORD=
```

## Начин на комуникација:

**GET** `http://127.0.0.1:8000/api/clients/4/summary`

Добиваме:

```json
{
    "client": {
        "id": 4,
        "name": "Stojan Pecanov",
        "created_at": "2026-09-04T14:41:55.000000Z",
        "updated_at": "2026-09-04T14:41:55.000000Z"
    },
    "cash_balance": "8750.00",
    "holdings": [
        {
            "instrument": "AAPL",
            "quantity": "7"
        }
    ]
}
```

**POST** `http://127.0.0.1:8000/api/transactions`

Body:

```json
{
    "client_id": 2,
    "type": "buy",
    "instrument": "AAPL",
    "quantity": 10,
    "price": 200
}
```

## Зошто ова го правиме

```php
$client = Client::where('id', $validated['client_id'])
                ->lockForUpdate()
                ->first();
```

Ова ни е потребно бидејќи сакаме да спречиме 2 трансакции да се извршат
истовремено поради тоа што може да имаме не точни информации за балансот на клиентот.

```php
$cashBalance = Transactions::where('client_id', $client->id)
                ->selectRaw("
                    COALESCE(SUM(
                        CASE
                            WHEN type IN ('deposit', 'sell') THEN amount
                            WHEN type IN ('withdrawal', 'buy') THEN -amount
                            ELSE 0
                        END
                    ), 0) as balance
                ")
                ->value('balance');
```

Со ова вршиме калкулација на балансот на соодветен клиент со помош на sql

Доколку type е deposit или sell го собираме износот, доколку е withdrawal или buy одзимаме

```php
$ownedQuantity = Transactions::where('client_id', $client->id)
                ->where('instrument', $validated['instrument'])
                ->selectRaw("
                    COALESCE(SUM(
                        CASE
                            WHEN type = 'buy' THEN quantity
                            WHEN type = 'sell' THEN -quantity
                            ELSE 0
                        END
                    ), 0) as quantity
                ")
                ->value('quantity');
```

Овде добиваме преку sql за количеството на акции на секој клиент.

Зошто не ги зачувуваме овие податоци во базата на податоци:

1. Бидејќи сакаме да ја користиме базата на податоци како единствен извор на вистина.
2. Не чуваме колку пари има секој клиент што придонесува до тоа доколку биде "пробиена" базата на податоци не може да видат колку пари има секој клиент (треба да ги следат сите трансакции доколку сакат да откријат).
3. Имаме конзистентост во податоците што ги добиваме
