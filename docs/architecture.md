# Architecture

## Minimal Structure

- `routes/api.php`
- `AvailabilityController`
- `HoldController`
- `SlotService`
- миграции `slots` и `holds`
- custom JWT auth layer

## Data Model

### `slots`

Поля минимального решения:

- `id`
- `capacity`
- `held_count`
- `confirmed_count`
- `start_at`
- `end_at`
- `created_at`
- `updated_at`

Доступность рассчитывается как:

- `capacity - held_count - confirmed_count`

### `holds`

Поля минимального решения:

- `id`
- `slot_id`
- `user_id`
- `status`
- `quantity`
- `idempotency_key`
- `expires_at`
- `created_at`
- `updated_at`

Ограничения:

- индекс по `slot_id`;
- индекс по `user_id`;
- составной уникальный индекс по `user_id` и `idempotency_key`.

### `users`

Так как авторизация нужна уже в первой версии, проект должен использовать пользовательскую модель Laravel с собственной минимальной JWT-аутентификацией без внешнего heavy package.

Минимально потребуется:

- endpoint логина или тестовый способ выдачи токена;
- сервис подписи и валидации JWT;
- auth middleware;
- привязка hold-операций к текущему пользователю.

## Transaction Model

### Hold creation

- аутентифицировать пользователя;
- открыть транзакцию;
- взять слот под блокировку;
- проверить доступность по формуле `capacity - held_count - confirmed_count`;
- проверить идемпотентность;
- создать hold c `quantity`;
- увеличить `held_count` на `quantity`;
- закоммитить;
- инвалидировать кеш.

### Confirm

- аутентифицировать пользователя;
- открыть транзакцию;
- взять hold под блокировку;
- проверить статус и expiry;
- если hold expired, уменьшить `held_count` на `quantity`, перевести hold в `expired`, вернуть `409 Conflict`;
- уменьшить `held_count` на `quantity`;
- увеличить `confirmed_count` на `quantity`;
- перевести `held -> confirmed`;
- закоммитить;
- инвалидировать кеш.

### Cancel

- аутентифицировать пользователя;
- открыть транзакцию;
- взять hold под блокировку;
- если hold в `held`, уменьшить `held_count` на `quantity`;
- если hold в `confirmed`, базовое поведение пока не фиксируем без отдельного решения;
- перевести hold в `cancelled`;
- закоммитить;
- инвалидировать кеш.

## Cache Model

- кешируется агрегированный ответ availability;
- TTL выбрать в диапазоне 5–15 секунд;
- ключ кеша должен быть стабильным и простым;
- для защиты от stampede использовать блокировку на пересборку кеша;
- локальный и базовый runtime cache driver: Redis.

## Main Risks

- неправильная модель счетчиков `held` / `confirmed` / `remaining`;
- некорректный перевод `held_count -> confirmed_count` на confirm;
- несогласованность кеша после confirm/cancel;
- гонки при параллельных hold-запросах;
- реактивная обработка expired hold на confirm должна быть идемпотентной и не должна дважды уменьшать `held_count`;
- неверная привязка hold к JWT-пользователю и idempotency scope.
