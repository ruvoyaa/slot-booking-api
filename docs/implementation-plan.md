# Implementation Plan

## Stage 1. Discovery Freeze

- зафиксировать оставшиеся правила повторных hold одного пользователя на один слот и политику хранения прошлого response для идемпотентности.

## Stage 2. Project Bootstrap

- создать Laravel проект;
- настроить `.env`;
- подключить MySQL;
- подключить Redis;
- поднять custom JWT auth;
- подготовить базовый README.

## Stage 3. Persistence

- миграция `slots`;
- миграция `users` / использование стандартной пользовательской модели;
- миграция `holds`;
- индексы и ограничения;
- базовые модели при необходимости;
- поля `held_count` и `confirmed_count` в `slots`.

## Stage 4. Service Layer

- реализовать `SlotService`;
- транзакции;
- идемпотентность;
- модель `held` / `confirmed` / availability через counters в `slots`;
- cache invalidation;
- cache lock.

## Stage 5. API Layer

- `GET /api/slots/availability`
- `POST /api/slots/{id}/hold`
- `POST /api/holds/{id}/confirm`
- `DELETE /api/holds/{id}`

## Stage 6. Tests

Минимальный набор:

- availability;
- hold success;
- hold idempotency;
- hold oversell conflict;
- confirm success;
- confirm expired/conflict path c реактивным освобождением `held_count`;
- cancel;
- auth-protected access;
- cache invalidation.

## Stage 7. Verification

- `php artisan migrate`
- `php artisan serve`
- curl-сценарии по README

## Stage 8. Review

- проверить, что нет оверсела;
- проверить корректность счетчиков `held_count` и `confirmed_count`;
- проверить, что кеш инвалидируется после state changes;
- проверить, что идемпотентность не ломает статусную модель.
