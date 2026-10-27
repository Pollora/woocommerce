<?php

declare(strict_types=1);

namespace Pollora\WooCommerce\Compatibility;

/**
 * Keeps the class names the framework used to ship resolving to the package.
 *
 * `Pollora\ThirdParty\WooCommerce\Domain\Models\Template` and
 * `Pollora\Events\WordPress\Plugins\WooCommerce\OrderStatusChanged` still load:
 * the first use aliases them to their `Pollora\WooCommerce\…` successor.
 *
 * The old names go away in the next major release.
 */
final class LegacyClassAliases
{
    /**
     * Old namespace prefix => new namespace prefix.
     *
     * @var array<string, string>
     */
    public const array NAMESPACES = [
        'Pollora\\Events\\WordPress\\Plugins\\WooCommerce\\' => 'Pollora\\WooCommerce\\Events\\',
        'Pollora\\ThirdParty\\WooCommerce\\' => 'Pollora\\WooCommerce\\',
    ];

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        spl_autoload_register(self::alias(...));
    }

    /**
     * Alias an old class name to the class that replaced it.
     *
     * @param  array<string, string>  $namespaces  Old namespace prefix => new namespace prefix
     */
    public static function alias(string $legacy, array $namespaces = self::NAMESPACES): void
    {
        foreach ($namespaces as $old => $new) {
            if (! str_starts_with($legacy, $old)) {
                continue;
            }

            $current = $new.substr($legacy, strlen($old));

            if (class_exists($current) || interface_exists($current)) {
                class_alias($current, $legacy);
            }

            return;
        }
    }
}
