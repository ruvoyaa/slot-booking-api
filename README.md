# slot-booking-api

Минимальный Laravel API бронирования слотов с:

- JWT-авторизацией;
- availability endpoint;
- созданием `hold`;
- подтверждением `hold`;
- отменой `hold`;
- защитой от оверсела через транзакции и счётчики;
- кешированием availability.

## Текущий статус

Сейчас в проекте уже реализованы:

- persistence layer;
- custom JWT auth skeleton;
- service layer;
- API layer;
- feature-тесты;
- отдельный project checklist;
- отдельный документ с зафиксированными test cases и фактическими результатами.

Актуальные проектные документы:

- [docs/project-context.md](docs/project-context.md)
- [docs/working-rules.md](docs/working-rules.md)
- [docs/spec.md](docs/spec.md)
- [docs/architecture.md](docs/architecture.md)
- [docs/implementation-plan.md](docs/implementation-plan.md)
- [docs/project-checklist.md](docs/project-checklist.md)
- [docs/test-cases.md](docs/test-cases.md)

## Стек

- Laravel 13
- PHP 8.4
- SQLite для локального bootstrap и тестов
- MySQL 8+ как целевой runtime по ТЗ
- custom minimal JWT auth
- Redis как целевой cache/lock backend

## Важная оговорка по кешу

Проектный дизайн ориентирован на Redis для `cache` и `lock`.

При этом в текущем локальном окружении:

- Redis extension/runtime не подтверждён;
- для локального выполнения `SlotService` использует безопасный fallback на `database` cache store;
- lock-механика в полном виде остаётся целевой для Redis-сценария.

Это важно учитывать при локальной проверке и перед production-like запуском.

## Быстрый старт

### 1. Установка зависимостей

```bash
composer install
```

### 2. Подготовка `.env`

Если файла `.env` ещё нет:

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Миграции и сидинг

```bash
php artisan migrate:fresh --seed
```

После этого будут созданы:

- тестовый пользователь;
- 2 demo slot;
- пустая таблица `holds`.

### 4. Локальный запуск

Локально:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Для внешнего доступа с сервера:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

## Тестовые учётные данные

Seeded user:

- email: `demo@example.com`
- password: `password`

## Основные endpoints

### Public

- `POST /api/auth/login`
- `GET /api/slots/availability`

### Protected

- `GET /api/auth/me`
- `POST /api/slots/{id}/hold`
- `POST /api/holds/{id}/confirm`
- `DELETE /api/holds/{id}`

## Curl-сценарии

### 1. Логин

```bash
curl -X POST http://127.0.0.1:8000/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"demo@example.com","password":"password"}'
```

Ожидаемо вернётся:

- `access_token`
- `token_type=Bearer`
- `expires_in`
- `user`

### 2. Проверка доступности слотов

```bash
curl http://127.0.0.1:8000/api/slots/availability
```

Пример ответа:

```json
[
  { "slot_id": 1, "capacity": 5, "remaining": 5 },
  { "slot_id": 2, "capacity": 10, "remaining": 10 }
]
```

### 3. Сохранить токен в переменную

```bash
TOKEN="<вставь access_token>"
```

### 4. Проверить текущего пользователя

```bash
curl http://127.0.0.1:8000/api/auth/me \
  -H "Authorization: Bearer $TOKEN"
```

### 5. Создать hold

```bash
curl -X POST http://127.0.0.1:8000/api/slots/1/hold \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Idempotency-Key: demo-hold-1' \
  -H 'Content-Type: application/json' \
  -d '{"quantity":2}'
```

Ожидаемо:

- `201 Created`
- `status=held`
- `quantity=2`

### 6. Повторить тот же hold с тем же `Idempotency-Key`

```bash
curl -X POST http://127.0.0.1:8000/api/slots/1/hold \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Idempotency-Key: demo-hold-1' \
  -H 'Content-Type: application/json' \
  -d '{"quantity":2}'
```

Ожидаемо:

- тот же hold;
- без повторного списания доступности.
- отдельный raw-snapshot старого ответа не хранится, возвращается текущее представление уже существующего hold.

### 6a. Создать несколько hold на один и тот же слот

Если у пользователя разные `Idempotency-Key`, несколько hold на один слот допустимы, пока хватает доступности.

### 7. Подтвердить hold

```bash
curl -X POST http://127.0.0.1:8000/api/holds/1/confirm \
  -H "Authorization: Bearer $TOKEN"
```

Ожидаемо:

- `200 OK`
- `status=confirmed`

### 8. Создать второй hold и отменить его

Создание:

```bash
curl -X POST http://127.0.0.1:8000/api/slots/1/hold \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Idempotency-Key: demo-hold-2' \
  -H 'Content-Type: application/json' \
  -d '{"quantity":1}'
```

Отмена:

```bash
curl -X DELETE http://127.0.0.1:8000/api/holds/2 \
  -H "Authorization: Bearer $TOKEN"
```

Ожидаемо:

- `200 OK`
- `status=cancelled`

### 9. Проверить конфликт по оверселу

```bash
curl -X POST http://127.0.0.1:8000/api/slots/1/hold \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Idempotency-Key: demo-hold-oversell' \
  -H 'Content-Type: application/json' \
  -d '{"quantity":999}'
```

Ожидаемо:

- `409 Conflict`

### 10. Проверить защищённость маршрутов

```bash
curl -X POST http://127.0.0.1:8000/api/slots/1/hold \
  -H 'Idempotency-Key: unauthorized-demo' \
  -H 'Content-Type: application/json' \
  -d '{"quantity":1}'
```

Ожидаемо:

- `401 Unauthorized`

## Запуск тестов

```bash
php artisan test
```

На текущем этапе:

- feature suite проходит полностью;
- результаты кейсов зафиксированы в [docs/test-cases.md](docs/test-cases.md).

## Что ещё остаётся

Следующие этапы по project checklist:

- Stage 8. README and curl scenarios
- Stage 9. Local verification
- Stage 10. Review and fixes
