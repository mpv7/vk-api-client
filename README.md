# vk-api-client

Типизированная, расширяемая и безопасная PHP-библиотека для VK API. Поддерживает методы
`messages.send`, `messages.getConversations` и `users.get`.

- PHP 8.1+ (readonly-свойства, enum, `new` в инициализаторах), `declare(strict_types=1)` везде
- Стандарт кода **PSR-12** (проверяется `phpcs` в CI)
- Зависимости (HTTP-клиент, токен, настройки, пауза, генератор `random_id`) передаются через конструктор (DI)
- Токен не хранится в коде: он берётся из переменной окружения, файла или своего провайдера
- Ошибки VK превращаются в понятные исключения PHP
- Повторные попытки при сетевых ошибках и временных ограничениях (HTTP 420/429, 5xx, ошибки VK 6 и 10)
- Тесты на PHPUnit 10 без обращения к сети

## Установка

```bash
composer require mpv7/vk-api-client
```

Пакет пока не опубликован на Packagist. Чтобы поставить его прямо из репозитория, добавьте в `composer.json`
вашего проекта:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/mpv7/vk-api-client" }
    ]
}
```

Требования: PHP 8.1+, расширения `json` и `mbstring`. Встроенный транспорт `CurlHttpClient` требует `curl`.
Если расширения нет, передайте свою реализацию `HttpClientInterface` (Guzzle, Symfony HttpClient и т. д.).

## Как получить токен

Библиотеке нужен ключ доступа VK с правом на сообщения. Есть два основных варианта:

1. **Токен сообщества.** Управление сообществом → Работа с API → Ключи доступа. Создайте ключ с правом
   «Сообщения сообщества» и включите сообщения сообщества в настройках.
2. **Пользовательский токен** с правом `messages`, полученный через авторизацию VK ID.

Актуальный порядок действий смотрите в документации VK (dev.vk.com). Токен никогда не коммитьте в репозиторий.

## Быстрый старт

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Mpv7\VkApi\Auth\EnvTokenProvider;
use Mpv7\VkApi\Http\CurlHttpClient;
use Mpv7\VkApi\VkClient;

// export VK_ACCESS_TOKEN=...
$client = new VkClient(new CurlHttpClient(), new EnvTokenProvider());

// Отправить сообщение (возвращает id сообщения)
$messageId = $client->sendMessage(123456789, 'Привет!');

// Получить диалоги: типизированные объекты Dialog
$dialogs = $client->getDialogs(offset: 0, count: 10);

echo 'Всего диалогов: ' . $dialogs->totalCount . PHP_EOL;

foreach ($dialogs as $dialog) {
    echo $dialog->title ?? ('peer ' . $dialog->peerId), ' (', $dialog->unreadCount, ' непрочитанных)', PHP_EOL;
    echo $dialog->lastMessage?->text ?? '-', PHP_EOL;
}

// Получить пользователя
$user = $client->getUser(123456789);
echo $user->getFullName(), ' ', $user->avatarUrl ?? '', PHP_EOL;
```

Готовый пример: [`examples/basic.php`](examples/basic.php).

## Токен и аутентификация

Клиент получает токен через `TokenProviderInterface` в момент запроса. Токен не «зашит» в класс.

| Провайдер | Откуда берёт токен |
|---|---|
| `EnvTokenProvider($variableName = 'VK_ACCESS_TOKEN')` | переменная окружения |
| `FileTokenProvider($path)` | файл (например, Docker/Kubernetes secret), пробелы и перевод строки обрезаются |
| `StaticTokenProvider($token)` | строка из памяти (например, из менеджера секретов); скрыта в `var_dump()` |

Свой провайдер: реализуйте `getToken(): string` и бросайте `AuthenticationException`, если токена нет.

Токен передаётся в **теле** POST-запроса, а не в URL, поэтому он не попадает в логи прокси и веб-серверов.
Базовый URL должен быть `https://`, иначе `ClientConfig` бросит исключение. Сообщения исключений и логи
библиотеки токен не содержат.

## Обработка ошибок

Все исключения наследуются от `Mpv7\VkApi\Exception\VkException`. Ошибки VK приходят как
`{"error": {"error_code": ..., "error_msg": ...}}` с HTTP 200, и библиотека переводит их в исключения:

| Код VK | Исключение | Повтор |
|---|---|---|
| 5 | `AuthorizationFailedException` | нет |
| 6 | `RateLimitException` | **да** (слишком много запросов в секунду) |
| 7, 15, 188 | `AccessDeniedException` | нет |
| 9, 29 | `RateLimitException` | нет (флуд-контроль, лимит метода) |
| 10 | `ServerErrorException` | **да** |
| 100, 113 | `InvalidParameterException` | нет |
| 900, 901, 902 | `MessageNotAllowedException` | нет |
| прочие | `ApiException` (с `getVkCode()` и `getVkMessage()`) | нет |

Остальные исключения:

- `NetworkException`: DNS, соединение, TLS, таймаут (повторяется)
- `HttpException`: неожиданный HTTP-статус; 420, 429, 500, 502, 503, 504 повторяются
- `InvalidResponseException`: ответ не JSON или без ожидаемых полей
- `AuthenticationException`: токен не найден на стороне клиента
- `UserNotFoundException`: `users.get` вернул пустой список
- `InvalidArgumentException` (стандартное): некорректные аргументы, запрос при этом не отправляется

```php
use Mpv7\VkApi\Exception\AccessDeniedException;
use Mpv7\VkApi\Exception\VkException;

try {
    $client->sendMessage($userId, 'Привет!');
} catch (AccessDeniedException $e) {
    // у токена нет прав
} catch (VkException $e) {
    // любые другие ошибки библиотеки
}
```

## Повторные попытки (Retry)

`RetryPolicy` задаёт экспоненциальную паузу: `base`, `2 * base`, `4 * base` и так далее, не больше `max`.
Заголовок `Retry-After` (в секундах) имеет приоритет, но тоже ограничен `max`.

```php
use Mpv7\VkApi\ClientConfig;
use Mpv7\VkApi\Retry\RetryPolicy;

$config = new ClientConfig(
    timeoutSeconds: 5.0,
    retryPolicy: new RetryPolicy(maxAttempts: 5, baseDelaySeconds: 0.5, maxDelaySeconds: 8.0),
);

$client = new VkClient(new CurlHttpClient(), new EnvTokenProvider(), $config);
```

`RetryPolicy::none()` отключает повторы. `maxAttempts` включает первую попытку.

**Защита от дублей.** `sendMessage` генерирует `random_id` один раз до первой попытки, и повторы используют
то же значение. VK не доставит сообщение повторно, даже если первый запрос дошёл, но ответ потерялся.
Свой `random_id` можно передать в `$options`.

## Публичный API

### `VkClientInterface` / `VkClient`

| Метод | Описание |
|---|---|
| `sendMessage(int $userId, string $message, array $options = []): int` | Отправляет текст (1–4096 символов), возвращает id сообщения. Для беседы передайте `2000000000 + chat_id`. `$options` передаются в `messages.send` (например, `['reply_to' => 123]`); `access_token`, `v`, `peer_id`, `message` переопределить нельзя |
| `getDialogs(int $offset = 0, int $count = 10): DialogCollection` | Страница диалогов (`count` от 1 до 200) |
| `getUser(int $userId): User` | Имя, фамилия и аватарка (`photo_200`) |

Метод `messages.getDialogs` закрыт VK, поэтому `getDialogs()` использует его преемника
`messages.getConversations` с `extended=1`.

### Модели (`Mpv7\VkApi\Model`)

- `DialogCollection`: `$totalCount`, `all(): list<Dialog>`, `count()`, `isEmpty()`, итерируется через `foreach`
- `Dialog`: `$peerId`, `$peerType` (enum `PeerType`), `$title`, `$unreadCount`, `$lastMessage` (`?Message`)
- `Message`: `$id`, `$peerId`, `$fromId`, `$date` (`DateTimeImmutable`), `$text`
- `User`: `$id`, `$firstName`, `$lastName`, `$avatarUrl` (`?string`), `getFullName()`

### Точки расширения

| Интерфейс | Назначение |
|---|---|
| `HttpClientInterface` | свой HTTP-транспорт |
| `TokenProviderInterface` | свой источник токена |
| `SleeperInterface` | пауза между повторами |
| `RandomIdGeneratorInterface` | источник `random_id` |

### `ClientConfig`

`apiVersion` (по умолчанию `5.199`), `baseUrl` (`https://api.vk.com/method`), `timeoutSeconds` (`10.0`),
`retryPolicy`. Если VK обновит API, версию можно поменять без правки кода библиотеки.

## Архитектура

```
src/
  VkClient.php, VkClientInterface.php, ClientConfig.php
  Auth/        TokenProviderInterface, Env/File/Static провайдеры
  Http/        HttpClientInterface, HttpResponse, CurlHttpClient
  Retry/       RetryPolicy, SleeperInterface, UsleepSleeper
  Util/        RandomIdGeneratorInterface, SecureRandomIdGenerator
  Model/       Dialog, DialogCollection, Message, User
  Enum/        VkErrorCode, PeerType
  Exception/   иерархия исключений
tests/
  Unit/        тесты
  Support/     фейки: FakeHttpClient, RecordingSleeper и др.
```

## Тесты и качество кода

```bash
composer install
composer test            # PHPUnit
composer test-coverage   # то же + покрытие (нужен pcov или xdebug)
composer cs              # PSR-12 (phpcs)
composer cs-fix          # автоисправление (phpcbf)
composer check           # cs + test
```

Тесты используют поддельный HTTP-клиент и «пустой» sleeper, поэтому сеть не нужна и они выполняются мгновенно.
Покрыты: успешные ответы всех методов, сетевые ошибки, исчерпание попыток, повторы при 420/429/5xx и ошибке VK 6,
`Retry-After`, ошибки доступа (7, 15, 188), авторизации (5), неизвестные коды, битый JSON и неожиданные структуры,
разбор `getDialogs` (пользователь, беседа, сообщество), валидация аргументов, провайдеры токена, отсутствие токена
в URL и сообщениях об ошибках, повтор с тем же `random_id`.

CI (GitHub Actions) запускает PSR-12 и тесты на PHP 8.1, 8.2 и 8.3.

## Лицензия

MIT, см. [LICENSE](LICENSE).
