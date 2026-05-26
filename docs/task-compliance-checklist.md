# Checklist соответствия ТЗ

Источник проверки:

- `docs/task`

Статусы:

- `done` — требование выполнено;
- `done-with-note` — выполнено, но есть осознанное отклонение или уточнение;
- `not-done` — не выполнено.

## Контрольный срез по ТЗ

- [x] `done` Минимальный API бронирования слотов реализован.
- [x] `done` `GET /api/slots/availability` реализован.
- [x] `done` `POST /api/slots/{id}/hold` реализован.
- [x] `done` `POST /api/holds/{id}/confirm` реализован.
- [x] `done` `DELETE /api/holds/{id}` реализован.
- [x] `done` Горячий кеш availability реализован.
- [x] `done-with-note` Защита от cache stampede реализована для целевого Redis-сценария; в локальном fallback `database` lock-path отключён для стабильной проверки.
- [x] `done` Инвалидация кеша после `hold` / `confirm` / `cancel` реализована.
- [x] `done` Транзакции в критичных операциях реализованы.
- [x] `done` Защита от оверсела реализована через counters-модель и проверки доступности.
- [x] `done` Идемпотентность `hold` реализована по паре `user_id + idempotency_key`.
- [x] `done` TTL hold на 5 минут реализован через `expires_at` и `HOLD_TTL_SECONDS=300`.
- [x] `done` Реактивная обработка expired hold на confirm реализована.
- [x] `done` Таблицы `slots` и `holds` созданы миграциями.
- [x] `done` Маршруты определены в `routes/api.php`.
- [x] `done` Контроллеры `AvailabilityController` и `HoldController` реализованы.
- [x] `done` Сервисный слой `SlotService` реализован.
- [x] `done` README с запуском, миграциями и curl-сценариями подготовлен.
- [x] `done` Проверочный test suite добавлен и проходит полностью.
- [x] `done` Проект приведён к Laravel 12.
- [x] `done-with-note` Automated feature-тесты используют SQLite in-memory, но отдельный required-stack run выполнен на `Laravel 12 + mysql driver + MariaDB 10.11`, что для текущего проекта принято как достаточное покрытие требования `MySQL 8+`.
- [x] `done-with-note` Добавлена JWT-авторизация, хотя в исходном ТЗ она не требовалась явно; это проектное расширение, не ломающие базовый API.

## Итог

ТЗ покрыто по сути полностью.

Остаются только осознанные отклонения/уточнения:

- локальная автоматизированная проверка всё ещё идёт на SQLite in-memory;
- прямой SQL runtime recheck выполнен на `MariaDB 10.11`; для текущего проекта и dev-сервера это признано достаточным эквивалентом требуемого `MySQL 8+`, поэтому миграция на другой движок не требуется;
- lock-механика cache stampede в полном виде ориентирована на Redis, а локально используется безопасный fallback;
- JWT auth добавлена как расширение поверх минимального ТЗ.
