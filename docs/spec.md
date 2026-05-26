# Specification

## Stack

- Laravel 13
- PHP 8.2+
- MySQL 8+
- custom minimal JWT auth
- Redis for cache and locks

## Endpoints

### `GET /api/slots/availability`

Возвращает список доступных слотов:

```json
[
  { "slot_id": 1, "capacity": 10, "remaining": 6 },
  { "slot_id": 2, "capacity": 5, "remaining": 0 }
]
```

Требования:

- кешировать ответ на 5–15 секунд;
- предусмотреть защиту от cache stampede;
- инвалидировать кеш после операций, меняющих доступность.

### `POST /api/slots/{id}/hold`

Заголовок:

```text
Idempotency-Key: <UUID>
```

Требования:

- создаёт hold со статусом `held`;
- требует авторизацию;
- проверяет доступность места;
- принимает количество резервируемых мест;
- при отсутствии доступности возвращает `409 Conflict`;
- повтор с тем же `Idempotency-Key` возвращает прежний результат.

### `POST /api/holds/{id}/confirm`

Требования:

- переводит hold в состояние `confirmed`;
- требует авторизацию;
- должен выполняться атомарно;
- не должен допускать оверсел;
- при конфликте по доступности возвращает `409 Conflict`;
- после успеха инвалидирует кеш доступности.

### `DELETE /api/holds/{id}`

Требования:

- переводит hold в состояние `cancelled`;
- требует авторизацию;
- возвращает слот в доступность согласно принятой модели остатков;
- инвалидирует кеш доступности.

## Domain Objects

### Slot

Минимально требуется:

- `id`
- `capacity`
- `start_at`
- `end_at`
- timestamps

### Hold

Минимально требуется:

- `id`
- `slot_id`
- `user_id`
- `status`
- `quantity`
- `idempotency_key`
- `expires_at`
- timestamps

Подтвержденный набор статусов:

- `held`
- `confirmed`
- `cancelled`
- `expired`

## Resolved Product Decisions

1. Одному пользователю разрешено иметь несколько hold на один слот, если используются разные `Idempotency-Key` и хватает доступности.
2. Полный прошлый raw-response для идемпотентности не хранится; при повторе возвращается существующий hold как текущий ресурс.

## Discovery Decisions

На текущий момент подтверждено:

- слот имеет `start_at` / `end_at`;
- hold может занимать больше одного места;
- нужна полноценная авторизация;
- для авторизации используем собственную минимальную JWT-реализацию без тяжёлого пакета;
- первая версия работает локально через `php artisan serve`, MySQL, `curl` и feature-тесты;
- для кеша и lock-механики используем Redis;
- требуется отдельная модель учета `held` и `confirmed`;
- базовая модель остатков: в `slots` храним `capacity`, `held_count`, `confirmed_count`, а доступность считаем как `capacity - held_count - confirmed_count`.
- при `confirm` просроченный hold реактивно освобождает ранее занятый `held_count` и возвращает `409 Conflict`.
- `Idempotency-Key` уникален в рамках `user_id`.
- одному пользователю разрешены несколько hold на один слот при разных `Idempotency-Key`;
- для идемпотентности не хранится отдельный снимок старого HTTP body, возвращается актуальное представление существующего hold.

## Fixed Capacity Model

Фиксируем базовую модель хранения остатков:

- в `slots` храним `capacity`, `held_count`, `confirmed_count`;
- в `holds` храним `quantity`, `status`, `user_id`, `idempotency_key`, `expires_at`;
- доступность считаем как `capacity - held_count - confirmed_count`.
- идемпотентность `hold` обеспечивается уникальностью пары `user_id + idempotency_key`.

Правила переходов:

- создание hold увеличивает `held_count` на `quantity`;
- confirm уменьшает `held_count` и увеличивает `confirmed_count`;
- cancel для `held` уменьшает `held_count`;
- cancel для `confirmed` в базовой версии запрещён или обрабатывается отдельным правилом позже;
- expired hold при попытке `confirm` реактивно освобождает ранее занятый `held_count` и возвращает `409 Conflict`.

Причина выбора:

- модель лучше соответствует требованию отдельного учета `held` и `confirmed`;
- не требует тяжелых агрегатов по `holds` на каждый availability-запрос;
- проще контролируется транзакционно под нагрузкой;
- лучше сочетается с горячим кешем availability.
