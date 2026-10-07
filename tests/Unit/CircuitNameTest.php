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
}
