<?php

namespace Gesflow\Sender\Http;

use Composer\InstalledVersions;
use Gesflow\Sender\Exceptions\ApiException;
use Gesflow\Sender\Exceptions\ConnectionException;
use Gesflow\Sender\Exceptions\MissingApiKeyException;
use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use SensitiveParameter;
use Throwable;

/**
 * Sends the requests to the GESFLOW SENDER API: authentication, JSON,
 * timeouts, retries and error mapping.
 *
 * Every POST carries an Idempotency-Key (generated when none is given) that
 * is reused by the retries. The platform forgets a key whose request ended
 * in an error, so a POST is only retried when it cannot have run: network
 * errors, 502/503/504 answered in front of the application, or 409 while a
 * previous attempt still holds the key. A 500 on a POST is never retried.
 */
class Connector
{
    public const USER_AGENT = 'GesflowSender-Laravel';

    public const PACKAGE = 'gesflow/sender-laravel';

    /**
     * Server errors answered before the application ran the request.
     */
    protected const RETRYABLE_POST_STATUSES = [502, 503, 504];

    public function __construct(
        protected Factory $http,
        protected string $baseUrl,
        #[SensitiveParameter] protected ?string $apiKey,
        protected int $timeout = 30,
        protected int $connectTimeout = 10,
        protected int $retries = 2,
        protected int $retryDelay = 500,
    ) {}

    /**
     * The same connector, authenticated with another API key.
     */
    public function withApiKey(#[SensitiveParameter] string $apiKey): static
    {
        $connector = clone $this;
        $connector->apiKey = $apiKey;

        return $connector;
    }

    /**
     * Installed version of the SDK, e.g. "1.1.0".
     */
    public static function version(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled(self::PACKAGE)) {
            $version = InstalledVersions::getPrettyVersion(self::PACKAGE);

            if ($version !== null && $version !== '') {
                return ltrim($version, 'v');
            }
        }

        return 'dev';
    }

    /**
     * User-Agent of the requests, so the platform knows the SDK version in use.
     */
    public static function userAgent(): string
    {
        return self::USER_AGENT.'/'.self::version().' PHP/'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $query = array_filter($query, fn (mixed $value): bool => $value !== null);

        return $this->send('GET', $path, $query === [] ? [] : ['query' => $query]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function post(string $path, array $data = [], ?string $idempotencyKey = null): array
    {
        return $this->send('POST', $path, $data === [] ? [] : ['json' => $data], $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * A multipart POST, to upload files along with the fields.
     *
     * @param  array<string, mixed>  $fields
     * @param  array<string, array{contents: string, filename: string}>  $files
     * @return array<string, mixed>
     */
    public function postMultipart(string $path, array $fields, array $files, ?string $idempotencyKey = null): array
    {
        $parts = self::multipartFields($fields);

        foreach ($files as $name => $file) {
            $parts[] = ['name' => $name, 'contents' => $file['contents'], 'filename' => $file['filename']];
        }

        return $this->send('POST', $path, ['multipart' => $parts], $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function put(string $path, array $data = []): array
    {
        return $this->send('PUT', $path, ['json' => $data]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function delete(string $path, array $data = []): array
    {
        return $this->send('DELETE', $path, $data === [] ? [] : ['json' => $data]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     *
     * @throws ApiException
     * @throws ConnectionException
     * @throws MissingApiKeyException
     */
    protected function send(string $method, string $path, array $options, ?string $idempotencyKey = null): array
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw MissingApiKeyException::make();
        }

        $request = $this->http
            ->baseUrl(rtrim($this->baseUrl, '/'))
            ->withToken($this->apiKey)
            ->acceptJson()
            ->withUserAgent(self::userAgent())
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->retry(
                $this->retries + 1,
                fn (int $attempt, ?Throwable $exception = null): int => $this->retryDelayFor($exception),
                fn (Throwable $exception): bool => $this->shouldRetry($exception, $method),
                throw: false,
            );

        if (isset($options['multipart'])) {
            $request = $request->asMultipart();
        }

        if ($idempotencyKey !== null) {
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        try {
            $response = $request->send($method, ltrim($path, '/'), $options);
        } catch (HttpConnectionException $exception) {
            throw ConnectionException::from($exception);
        }

        if ($response->failed()) {
            throw ApiException::fromResponse($response);
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }

    /**
     * Network errors, 409 (idempotency key still held by a previous attempt)
     * and server errors are retried; a server error on a write only when it
     * comes from in front of the application (502, 503, 504).
     */
    protected function shouldRetry(Throwable $exception, string $method): bool
    {
        if ($exception instanceof HttpConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException) {
            return false;
        }

        $status = $exception->response->status();

        if ($status === 409) {
            return true;
        }

        if (! $exception->response->serverError()) {
            return false;
        }

        return $method === 'GET' || in_array($status, static::RETRYABLE_POST_STATUSES, true);
    }

    /**
     * Milliseconds to wait before the next attempt: the configured delay, or
     * the Retry-After of a 409 response (capped at 5 seconds).
     */
    protected function retryDelayFor(?Throwable $exception): int
    {
        if ($exception instanceof RequestException && $exception->response->status() === 409) {
            return min(5000, max($this->retryDelay, 1000 * (int) $exception->response->header('Retry-After')));
        }

        return $this->retryDelay;
    }

    /**
     * Form fields of a multipart body: lists become repeated "name[]" parts
     * and booleans "1" or "0", as PHP and Laravel read them.
     *
     * @param  array<string, mixed>  $fields
     * @return list<array{name: string, contents: string}>
     */
    protected static function multipartFields(array $fields): array
    {
        $parts = [];

        foreach ($fields as $name => $value) {
            if ($value === null) {
                continue;
            }

            foreach (is_array($value) ? $value : [$value] as $item) {
                $parts[] = [
                    'name' => is_array($value) ? "{$name}[]" : $name,
                    'contents' => is_bool($item) ? ($item ? '1' : '0') : (is_scalar($item) ? (string) $item : ''),
                ];
            }
        }

        return $parts;
    }
}
