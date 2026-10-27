<?php

declare(strict_types=1);

use Pollora\WooCommerce\Compatibility\LegacyClassAliases;
use Pollora\WooCommerce\Domain\Contracts\TemplateResolverInterface;
use Pollora\WooCommerce\Domain\Models\Template;
use Pollora\WooCommerce\Events\OrderStatusChanged;

describe('LegacyClassAliases', function (): void {
    test('aliases an old class name to the class that replaced it', function (): void {
        LegacyClassAliases::alias('Legacy\\Shop\\Domain\\Models\\Template', ['Legacy\\Shop\\' => 'Pollora\\WooCommerce\\']);

        expect(new ReflectionClass('Legacy\\Shop\\Domain\\Models\\Template'))
            ->getName()->toBe(Template::class);
    });

    test('aliases an interface too', function (): void {
        LegacyClassAliases::alias('Legacy\\Shop\\Domain\\Contracts\\TemplateResolverInterface', ['Legacy\\Shop\\' => 'Pollora\\WooCommerce\\']);

        expect(interface_exists('Legacy\\Shop\\Domain\\Contracts\\TemplateResolverInterface', false))->toBeTrue()
            ->and((new ReflectionClass('Legacy\\Shop\\Domain\\Contracts\\TemplateResolverInterface'))->getName())
            ->toBe(TemplateResolverInterface::class);
    });

    test('maps the events namespace to its own prefix', function (): void {
        LegacyClassAliases::alias('Legacy\\Events\\OrderStatusChanged', ['Legacy\\Events\\' => 'Pollora\\WooCommerce\\Events\\']);

        expect((new ReflectionClass('Legacy\\Events\\OrderStatusChanged'))->getName())->toBe(OrderStatusChanged::class);
    });

    test('leaves a name outside the old namespaces alone', function (): void {
        LegacyClassAliases::alias('Unrelated\\Template', ['Legacy\\Shop\\' => 'Pollora\\WooCommerce\\']);

        expect(class_exists('Unrelated\\Template', false))->toBeFalse();
    });

    test('leaves a name alone when nothing replaced it', function (): void {
        LegacyClassAliases::alias('Legacy\\Shop\\Gone', ['Legacy\\Shop\\' => 'Pollora\\WooCommerce\\']);

        expect(class_exists('Legacy\\Shop\\Gone', false))->toBeFalse();
    });

    test('maps the namespaces the framework used to ship', function (): void {
        expect(LegacyClassAliases::NAMESPACES)->toBe([
            'Pollora\\Events\\WordPress\\Plugins\\WooCommerce\\' => 'Pollora\\WooCommerce\\Events\\',
            'Pollora\\ThirdParty\\WooCommerce\\' => 'Pollora\\WooCommerce\\',
        ]);
    });
});
