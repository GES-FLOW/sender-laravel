<?php

namespace Gesflow\Sender\Http;

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
 * is reused by the retries: a message is never sent twice because a
 * network error made the SDK try again.
 */
class Connector
{
    public const USER_AGENT = 'GesflowSender-Laravel/1.0';

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
            ->withUserAgent(self::USER_AGENT)
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->retry(
                $this->retries + 1,
                fn (int $attempt, ?Throwable $exception = null): int => $this->retryDelayFor($exception),
                fn (Throwable $exception): bool => $this->shouldRetry($exception),
                throw: false,
            );

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
     * Network errors, server errors and requests whose idempotency key is
     * still held by a previous attempt (409) are retried; the other errors
     * are returned at once.
     */
    protected function shouldRetry(Throwable $exception): bool
    {
        return $exception instanceof HttpConnectionException
            || ($exception instanceof RequestException && ($exception->response->serverError() || $exception->response->status() === 409));
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
}
