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

Повторная проверка после перевода проекта на `Laravel 12` выполнена на SQL runtime через `mysql` driver.

Актуальные проектные документы:

- [docs/project-context.md](docs/project-context.md)
- [docs/working-rules.md](docs/working-rules.md)
- [docs/spec.md](docs/spec.md)
- [docs/architecture.md](docs/architecture.md)
- [docs/implementation-plan.md](docs/implementation-plan.md)
- [docs/project-checklist.md](docs/project-checklist.md)
- [docs/test-cases.md](docs/test-cases.md)

## Стек

- Laravel 12
- PHP 8.4
- MySQL 8+ как целевой runtime по ТЗ
- SQLite in-memory используется в automated feature tests
- custom minimal JWT auth
- Redis как целевой cache/lock backend

Фактически локальный SQL runtime, на котором выполнен второй verification pass:

- `MariaDB 10.11` через `mysql` driver

Для текущего проекта этого достаточно: `MariaDB 10.11` совместима с используемым здесь подмножеством `MySQL 8` на нужном нам уровне проверки, поэтому на dev-сервере менять её не требуется.

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

Заполни MySQL-подключение в `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=slot_booking_api
DB_USERNAME=<user>
DB_PASSWORD=<password>
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

## Статус project checklist

Все этапы MVP по `docs/project-checklist.md` на текущий момент закрыты, включая:

- Stage 8. README and curl scenarios
- Stage 9. Local verification
- Stage 10. Review and fixes

## Checklist соответствия ТЗ

Полный отдельный срез лежит в `docs/task-compliance-checklist.md`.

Короткий итог по `docs/task`:

- [x] Все основные API endpoints из ТЗ реализованы.
- [x] Кеш availability, инвалидация кеша, транзакции и защита от оверсела реализованы.
- [x] Идемпотентность `hold` реализована.
- [x] TTL hold на 5 минут реализован.
- [x] Миграции, `routes/api.php`, контроллеры и `SlotService` реализованы.
- [x] README и curl-сценарии подготовлены.
- [x] Feature-тесты добавлены и проходят.
- [x] ТЗ по сути закрыто.

Оговорки по соответствию ТЗ:

- Приложение переведено на `Laravel 12`.
- Automated feature-тесты по-прежнему идут на `SQLite in-memory`, но целевой runtime по ТЗ остаётся `MySQL 8+`.
- Прямая локальная SQL-верификация подтверждена на `MariaDB 10.11` через `mysql` driver; для текущего проекта и dev-сервера это считаем достаточным эквивалентом требуемого `MySQL 8+`, поэтому замену СУБД не планируем.
- Полная lock-механика cache stampede ориентирована на `Redis`; локально используется fallback.
- `JWT` добавлена как проектное расширение, хотя в исходном ТЗ не была обязательной.
