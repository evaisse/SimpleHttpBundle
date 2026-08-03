<?php

namespace evaisse\SimpleHttpBundle\Service;

use evaisse\SimpleHttpBundle\DataCollector\ProfilerDataCollector;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\HttpKernel\Profiler\Profile;

class ReplayRequestResolver
{
    public function __construct(private ?Profiler $profiler = null)
    {
    }

    public function isAvailable(): bool
    {
        return null !== $this->profiler;
    }

    /**
     * @return array{
     *     method: string,
     *     uri: string,
     *     content: string,
     *     headers: string[],
     *     cookies: array<string, mixed>
     * }
     */
    public function resolve(string $token, int $callIndex): array
    {
        if (!$this->profiler) {
            throw new NotFoundHttpException('Profiler is not available.');
        }

        $profile = $this->profiler->loadProfile($token);
        if (!$profile instanceof Profile) {
            throw new NotFoundHttpException('Profiler token not found.');
        }

        if (!$profile->hasCollector('simplehttpprofiler')) {
            throw new NotFoundHttpException('Simple HTTP collector not found.');
        }

        $collector = $profile->getCollector('simplehttpprofiler');
        if (!$collector instanceof ProfilerDataCollector) {
            throw new NotFoundHttpException('Simple HTTP collector is invalid.');
        }

        $calls = $collector->getCalls();
        if (!array_key_exists($callIndex, $calls) || !is_array($calls[$callIndex])) {
            throw new NotFoundHttpException('HTTP call not found.');
        }

        $request = $calls[$callIndex]['request'] ?? null;
        if (!is_array($request)) {
            throw new NotFoundHttpException('Stored request is invalid.');
        }

        $method = isset($request['method']) && is_string($request['method']) ? $request['method'] : '';
        $uri = $this->resolveUri($request);

        if ('' === $method || '' === $uri) {
            throw new NotFoundHttpException('Stored request is incomplete.');
        }

        return [
            'method' => $method,
            'uri' => $uri,
            'content' => isset($request['content']) && is_string($request['content']) ? $request['content'] : '',
            'headers' => $this->normalizeHeaders($request['headers'] ?? []),
            'cookies' => is_array($request['cookies'] ?? null) ? $request['cookies'] : [],
        ];
    }

    /**
     * @param mixed $headers
     * @return string[]
     */
    private function normalizeHeaders(mixed $headers): array
    {
        if (!is_array($headers)) {
            return [];
        }

        return array_values(array_filter($headers, static fn (mixed $header): bool => is_string($header)));
    }

    /**
     * @param array<string, mixed> $request
     */
    private function resolveUri(array $request): string
    {
        if (isset($request['uri']) && is_string($request['uri']) && '' !== $request['uri']) {
            return $request['uri'];
        }

        $schemeAndHttpHost = $request['schemeAndHttpHost'] ?? null;
        $requestUri = $request['requestUri'] ?? null;

        if (is_string($schemeAndHttpHost) && is_string($requestUri) && '' !== $schemeAndHttpHost && '' !== $requestUri) {
            return $schemeAndHttpHost . $requestUri;
        }

        return '';
    }
}
