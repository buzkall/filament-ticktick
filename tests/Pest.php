<?php

use Arzcode\FilamentTicktick\Tests\TestCase;
use Arzcode\TickTick\TickTick;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

uses(TestCase::class)->in('Feature');

/**
 * Binds a TickTick client whose HTTP calls are answered by "METHOD /path" routes,
 * recording every request into $history. Unmatched routes answer with an empty
 * JSON array.
 *
 * @param  array<string, array|Response>  $routes
 */
function fakeTickTick(array $routes = [], ?array &$history = null): void
{
    $history = [];

    $stack = HandlerStack::create(function(RequestInterface $request) use ($routes) {
        $response = $routes[$request->getMethod() . ' ' . $request->getUri()->getPath()] ?? [];

        return Create::promiseFor($response instanceof Response ? $response : jsonResponse($response));
    });
    $stack->push(Middleware::history($history));

    app()->instance(TickTick::class, new TickTick([
        'access_token' => 'test_access_token',
        'handler'      => $stack,
    ]));
}

function jsonResponse(array $body, int $status = 200): Response
{
    return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
}

/**
 * "METHOD /path" of every recorded request, ignoring the project and folder lists the form loads.
 */
function sentRequests(array $history): array
{
    return collect($history)
        ->map(fn(array $entry) => $entry['request']->getMethod() . ' ' . $entry['request']->getUri()->getPath())
        ->reject(fn(string $request) => in_array($request, ['GET /open/v1/project', 'GET /open/v1/project/group'], true))
        ->values()
        ->all();
}

/**
 * Decoded JSON body of the first recorded request to "METHOD /path".
 */
function sentPayload(array $history, string $route): array
{
    $entry = collect($history)->first(fn(array $entry) => $entry['request']->getMethod() . ' ' . $entry['request']->getUri()->getPath() === $route);

    return json_decode((string)$entry['request']->getBody(), true);
}
