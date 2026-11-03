<?php

declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Pollora\WooCommerce\Infrastructure\Services\ComingSoonHandler;

beforeEach(function (): void {
    $this->productTemplate = '/theme/woocommerce/single-product.blade.php';
    $this->comingSoonTemplate = '/theme/woocommerce/coming-soon.blade.php';
    $this->shareKey = 'test-share-key';

    $this->wooCommerceOptions = [
        'woocommerce_coming_soon' => 'yes',
        'woocommerce_store_pages_only' => 'no',
        'woocommerce_private_link' => 'yes',
        'woocommerce_share_key' => $this->shareKey,
    ];

    Functions\when('get_option')->alias(fn (string $name, mixed $default = false): mixed => $this->wooCommerceOptions[$name] ?? $default);
    Functions\when('current_user_can')->justReturn(false);

    $viewFactory = Mockery::mock(ViewFactory::class);
    $viewFactory->shouldReceive('exists')->with('woocommerce.coming-soon')->andReturn(true);
    $viewFactory->shouldReceive('make')->with('woocommerce.coming-soon')->andReturn(
        Mockery::mock(['getPath' => $this->comingSoonTemplate])
    );

    $this->handler = new ComingSoonHandler($viewFactory);
});

afterEach(function (): void {
    unset($_GET['woo-share'], $_COOKIE['woo-share'], $_COOKIE['woocommerce_share_key']);
});

describe('ComingSoonHandler::handleTemplateInclude()', function (): void {
    it('shows the coming soon page to a visitor without the private link', function (): void {
        expect($this->handler->handleTemplateInclude($this->productTemplate))->toBe($this->comingSoonTemplate);
    });

    it('lets a visitor in with the share key in the woo-share query parameter', function (): void {
        $_GET['woo-share'] = $this->shareKey;

        expect($this->handler->handleTemplateInclude($this->productTemplate))->toBe($this->productTemplate);
    });

    it('lets a visitor in with the share key in the woo-share cookie', function (): void {
        $_COOKIE['woo-share'] = $this->shareKey;

        expect($this->handler->handleTemplateInclude($this->productTemplate))->toBe($this->productTemplate);
    });

    it('ignores a cookie named after the woocommerce_share_key option', function (): void {
        $_COOKIE['woocommerce_share_key'] = $this->shareKey;

        expect($this->handler->handleTemplateInclude($this->productTemplate))->toBe($this->comingSoonTemplate);
    });

    it('shows the coming soon page for a wrong share key', function (): void {
        $_GET['woo-share'] = 'not-the-key';
        $_COOKIE['woo-share'] = 'not-the-key';

        expect($this->handler->handleTemplateInclude($this->productTemplate))->toBe($this->comingSoonTemplate);
    });

    it('shows the coming soon page when the private link is disabled', function (): void {
        $this->wooCommerceOptions['woocommerce_private_link'] = 'no';
        $_GET['woo-share'] = $this->shareKey;

        expect($this->handler->handleTemplateInclude($this->productTemplate))->toBe($this->comingSoonTemplate);
    });

    it('keeps the template of a request a plugin excludes from coming soon mode', function (): void {
        Filters\expectApplied('woocommerce_coming_soon_exclude')->once()->with(false)->andReturn(true);

        expect($this->handler->handleTemplateInclude($this->productTemplate))->toBe($this->productTemplate);
    });
});
