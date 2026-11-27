<?php

declare(strict_types=1);

use Pollora\WooCommerce\Domain\Contracts\WooCommerceServiceInterface;
use Pollora\WooCommerce\Domain\Services\WooCommerceService;
use Pollora\WooCommerce\Infrastructure\Adapters\WordPressWooCommerceAdapter;
use Pollora\WooCommerce\Infrastructure\Contracts\WooCommerceAdapterInterface;

it('keeps its two WordPress seams final, replaceable through their contract', function (string $class, string $contract): void {
    $reflection = new ReflectionClass($class);
    $names = static fn (string $type): array => array_values(array_filter(
        array_map(static fn (ReflectionMethod $method): string => $method->getName(), (new ReflectionClass($type))->getMethods(ReflectionMethod::IS_PUBLIC)),
        static fn (string $name): bool => $name !== '__construct',
    ));

    expect($reflection->isFinal())->toBeTrue()
        ->and($reflection->implementsInterface($contract))->toBeTrue()
        ->and($names($contract))->toEqualCanonicalizing($names($class));
})->with([
    'the template service' => [WooCommerceService::class, WooCommerceServiceInterface::class],
    'the WordPress adapter' => [WordPressWooCommerceAdapter::class, WooCommerceAdapterInterface::class],
]);
