<?php

declare(strict_types=1);

use Pollora\WooCommerce\Domain\Contracts\WooCommerceServiceInterface;
use Pollora\WooCommerce\Domain\Models\Template;
use Pollora\WooCommerce\Infrastructure\Services\WooCommerceTemplateResolver;

describe('WooCommerceTemplateResolver duplicates', function (): void {
    it('lists each Blade template once, however many times it is found', function (): void {
        $domainService = Mockery::mock(WooCommerceServiceInterface::class);
        $domainService->shouldReceive('addBladeVariants')->andReturn([
            'archive-product.blade.php',
            'archive-product.blade.php',
            'resources/views/archive-product.blade.php',
        ]);
        $domainService->shouldReceive('createTemplate')->with('archive-product.php')
            ->andReturn(new Template('archive-product.php', 'archive-product', false));
        $domainService->shouldReceive('getWooCommerceTemplatePath')->andReturn('woocommerce/');

        $result = (new WooCommerceTemplateResolver($domainService))->extendTemplateLoaderFiles(['single.php'], 'archive-product.php');

        expect(array_values($result))->toBe([
            'archive-product.blade.php',
            'resources/views/archive-product.blade.php',
            'resources/views/woocommerce/archive-product.blade.php',
            'single.php',
        ]);
    });
});
