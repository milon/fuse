<?php

declare(strict_types=1);

namespace Milon\Fuse\Testing;

use Saloon\Http\Connector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

/**
 * Saloon mock helpers that leave spare responses for open-circuit rejects.
 *
 * Saloon's mock middleware runs before Fuse. A send that should throw
 * {@see \Milon\Fuse\CircuitOpenException} still needs a spare mock entry,
 * or Saloon throws {@see \Saloon\Exceptions\NoMockResponseFoundException} first.
 */
final class FuseMockClient
{
    /**
     * @param  array<array-key, MockResponse|callable>  $responses
     */
    public static function sequence(array $responses, int $openRejectSpares = 1, ?MockResponse $spare = null): MockClient
    {
        $padded = $responses;

        for ($i = 0; $i < max(0, $openRejectSpares); $i++) {
            $padded[] = $spare ?? MockResponse::make(body: '', status: 200);
        }

        return new MockClient($padded);
    }

    /**
     * @param  array<array-key, MockResponse|callable>  $responses
     */
    public static function mock(
        Connector $connector,
        array $responses,
        int $openRejectSpares = 1,
        ?MockResponse $spare = null,
    ): MockClient {
        $client = self::sequence($responses, $openRejectSpares, $spare);
        $connector->withMockClient($client);

        return $client;
    }
}
