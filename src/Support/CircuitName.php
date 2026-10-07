<?php

declare(strict_types=1);

namespace Milon\Fuse\Support;

final class CircuitName
{
    /**
     * Derive a circuit name from a Saloon connector class.
     *
     * `BillingConnector` → `billing`, `BillingSdkConnector` → `billing-sdk`.
     * Classes that do not end in `Connector` keep their short name in kebab-case.
     */
    public static function fromConnectorClass(string $class): string
    {
        $position = strrpos($class, '\\');
        $short = $position === false ? $class : substr($class, $position + 1);

        if (str_ends_with($short, 'Connector') && strlen($short) > strlen('Connector')) {
            $short = substr($short, 0, -strlen('Connector'));
        }

        return self::toKebab($short);
    }

    private static function toKebab(string $value): string
    {
        $kebab = preg_replace('/(?<=\w)([A-Z])/', '-$1', $value);

        return strtolower($kebab ?? $value);
    }
}
