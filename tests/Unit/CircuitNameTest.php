<?php

declare(strict_types=1);

namespace Milon\Fuse\Tests\Unit;

use Milon\Fuse\Support\CircuitName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CircuitNameTest extends TestCase
{
    #[Test]
    #[DataProvider('connectorClasses')]
    public function it_derives_kebab_names_from_connector_classes(string $class, string $expected): void
    {
        $this->assertSame($expected, CircuitName::fromConnectorClass($class));
    }

    #[Test]
    #[DataProvider('requestClasses')]
    public function it_derives_kebab_names_from_request_classes(string $class, string $expected): void
    {
        $this->assertSame($expected, CircuitName::fromRequestClass($class));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function connectorClasses(): array
    {
        return [
            'billing connector' => ['App\\Http\\Integrations\\BillingConnector', 'billing'],
            'compound name' => ['BillingSdkConnector', 'billing-sdk'],
            'no connector suffix' => ['App\\Clients\\SearchApi', 'search-api'],
            'connector alone' => ['Connector', 'connector'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function requestClasses(): array
    {
        return [
            'charge request' => ['App\\Http\\Integrations\\ChargeRequest', 'charge'],
            'compound name' => ['CreateInvoiceRequest', 'create-invoice'],
            'no request suffix' => ['App\\Http\\Charge', 'charge'],
            'request alone' => ['Request', 'request'],
        ];
    }
}
