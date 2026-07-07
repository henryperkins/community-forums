<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Service\OAuth\HttpClient;
use RuntimeException;
use Throwable;

/**
 * Scripted stand-in for the outbound OAuth/OIDC HTTP client. Each URL carries
 * a FIFO queue of responses (the last one sticks once the queue drains, so
 * rotation scenarios can script "old JWKS, then new JWKS"). Scripting a
 * Throwable makes that call throw — provider-outage fixtures. Unscripted URLs
 * throw, so a test can never silently reach the network.
 */
final class ScriptedOAuthHttpClient extends HttpClient
{
    /** @var array<string,list<array<string,mixed>|Throwable>> */
    private array $queues = [];

    /** @var list<array{method:string,url:string,form?:array<string,string>}> */
    public array $calls = [];

    /** @param array<string,mixed>|Throwable ...$responses */
    public function script(string $url, array|Throwable ...$responses): void
    {
        foreach ($responses as $response) {
            $this->queues[$url][] = $response;
        }
    }

    /** Drop anything pending for the URL and script fresh — per-leg re-scripting. */
    public function replace(string $url, array|Throwable ...$responses): void
    {
        unset($this->queues[$url]);
        $this->script($url, ...$responses);
    }

    public function getJson(string $url, ?string $bearer = null): array
    {
        $this->calls[] = ['method' => 'GET', 'url' => $url];
        return $this->take($url);
    }

    public function postForm(string $url, array $form, array $headers = []): array
    {
        $this->calls[] = ['method' => 'POST', 'url' => $url, 'form' => $form];
        return $this->take($url);
    }

    /** @return list<string> URLs fetched, in order */
    public function urls(): array
    {
        return array_map(static fn (array $c): string => $c['url'], $this->calls);
    }

    /** @return array<string,mixed> */
    private function take(string $url): array
    {
        $queue = $this->queues[$url] ?? [];
        if ($queue === []) {
            throw new RuntimeException('OAuth HTTP request failed: unscripted URL ' . $url);
        }
        $response = count($queue) > 1 ? array_shift($this->queues[$url]) : $queue[0];
        if ($response instanceof Throwable) {
            throw $response;
        }
        return $response;
    }
}
