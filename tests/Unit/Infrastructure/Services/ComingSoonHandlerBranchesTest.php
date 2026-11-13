<?php

declare(strict_types=1);

use Automattic\WooCommerce\Internal\ComingSoon\ComingSoonRequestHandler;
use Brain\Monkey\Functions;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Pollora\WooCommerce\Infrastructure\Services\ComingSoonHandler;

beforeEach(function (): void {
    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, bool>  $views  view name => exists
     */
    $this->makeHandler = function (array $options, array $views = ['woocommerce.coming-soon' => true], ?Throwable $makeFails = null, bool $canManage = false): ComingSoonHandler {
        Functions\when('get_option')->alias(fn (string $name, mixed $default = false): mixed => $options[$name] ?? $default);
        Functions\when('current_user_can')->alias(fn (string $capability): bool => $canManage && $capability === 'manage_woocommerce');

        $viewFactory = Mockery::mock(ViewFactory::class);
        foreach ($views as $name => $exists) {
            $viewFactory->shouldReceive('exists')->with($name)->andReturn($exists);
            if ($exists) {
                if ($makeFails instanceof Throwable) {
                    $viewFactory->shouldReceive('make')->with($name)->andThrow($makeFails);
                } else {
                    $viewFactory->shouldReceive('make')->with($name)->andReturn(Mockery::mock(['getPath' => "/theme/{$name}.blade.php"]));
                }
            }
        }
        $viewFactory->shouldReceive('exists')->andReturn(false)->byDefault();

        return new ComingSoonHandler($viewFactory);
    };

    /**
     * Define the store-page conditionals of WooCommerce, all false unless told otherwise.
     *
     * @param  array<string, mixed>  $overrides
     */
    $this->storeConditionals = function (array $overrides = []): void {
        $values = $overrides + [
            'is_404' => false,
            'is_woocommerce' => false,
            'is_cart' => false,
            'is_checkout' => false,
            'is_account_page' => false,
            'is_shop' => false,
            'wc_terms_and_conditions_page_id' => 0,
        ];

        foreach ($values as $function => $value) {
            Functions\when($function)->justReturn($value);
        }
        Functions\when('is_page')->alias(fn (int $page): bool => $page === 77);
    };

    $this->template = '/theme/page.php';
    $this->comingSoon = '/theme/woocommerce.coming-soon.blade.php';
    $this->storeOnly = ['woocommerce_coming_soon' => 'yes', 'woocommerce_store_pages_only' => 'yes'];
});

afterEach(function (): void {
    unset($_GET['woo-share'], $_COOKIE['woo-share'], $GLOBALS['wp_filter']);
});

describe('ComingSoonHandler when the whole site is coming soon', function (): void {
    it('keeps the template when coming soon mode is off', function (): void {
        $handler = ($this->makeHandler)(['woocommerce_coming_soon' => 'no']);

        expect($handler->handleTemplateInclude($this->template))->toBe($this->template);
    });

    it('keeps the template for a user who can manage WooCommerce', function (): void {
        $handler = ($this->makeHandler)(['woocommerce_coming_soon' => 'yes'], canManage: true);

        expect($handler->handleTemplateInclude($this->template))->toBe($this->template);
    });

    it('shows the coming soon page when the private link has no share key', function (string $key): void {
        $handler = ($this->makeHandler)(['woocommerce_coming_soon' => 'yes', 'woocommerce_private_link' => 'yes', 'woocommerce_share_key' => $key]);
        $_GET['woo-share'] = $key;

        expect($handler->handleTemplateInclude($this->template))->toBe('/theme/woocommerce.coming-soon.blade.php');
    })->with(['empty' => [''], 'zero' => ['0']]);

    it('keeps the template when no Blade coming soon view exists', function (): void {
        $handler = ($this->makeHandler)(['woocommerce_coming_soon' => 'yes'], ['woocommerce.coming-soon' => false]);

        expect($handler->handleTemplateInclude($this->template))->toBe($this->template);
    });

    it('keeps the template when the view cannot be made', function (): void {
        $handler = ($this->makeHandler)(['woocommerce_coming_soon' => 'yes'], makeFails: new InvalidArgumentException('View not found'));

        expect($handler->handleTemplateInclude($this->template))->toBe($this->template);
    });
});

describe('ComingSoonHandler when only the store pages are coming soon', function (): void {
    it('uses the store-only view when the theme has one', function (): void {
        ($this->storeConditionals)(['is_woocommerce' => true]);
        $handler = ($this->makeHandler)($this->storeOnly, ['woocommerce.coming-soon-store-only' => true, 'woocommerce.coming-soon' => true]);

        expect($handler->handleTemplateInclude($this->template))->toBe('/theme/woocommerce.coming-soon-store-only.blade.php');
    });

    it('falls back to the generic view when the theme has no store-only view', function (): void {
        ($this->storeConditionals)(['is_woocommerce' => true]);
        $handler = ($this->makeHandler)($this->storeOnly, ['woocommerce.coming-soon-store-only' => false, 'woocommerce.coming-soon' => true]);

        expect($handler->handleTemplateInclude($this->template))->toBe('/theme/woocommerce.coming-soon.blade.php');
    });

    it('keeps the template on a 404 page', function (): void {
        ($this->storeConditionals)(['is_404' => true, 'is_woocommerce' => true]);
        $handler = ($this->makeHandler)($this->storeOnly);

        expect($handler->handleTemplateInclude($this->template))->toBe($this->template);
    });

    it('shows coming soon on a page that is a store page', function (string $conditional): void {
        ($this->storeConditionals)([$conditional => true]);
        $handler = ($this->makeHandler)($this->storeOnly);

        expect($handler->handleTemplateInclude($this->template))->toBe('/theme/woocommerce.coming-soon.blade.php');
    })->with(['is_woocommerce', 'is_cart', 'is_checkout', 'is_account_page', 'is_shop']);

    it('shows coming soon on the terms and conditions page', function (): void {
        ($this->storeConditionals)(['wc_terms_and_conditions_page_id' => 77]);
        $handler = ($this->makeHandler)($this->storeOnly);

        expect($handler->handleTemplateInclude($this->template))->toBe('/theme/woocommerce.coming-soon.blade.php');
    });

    it('keeps the template on a page that is not a store page', function (): void {
        ($this->storeConditionals)(['wc_terms_and_conditions_page_id' => 12]);
        $handler = ($this->makeHandler)($this->storeOnly);

        expect($handler->handleTemplateInclude($this->template))->toBe($this->template);
    });

    it('keeps the template when the site has no terms and conditions page', function (): void {
        ($this->storeConditionals)(['wc_terms_and_conditions_page_id' => 0]);
        $handler = ($this->makeHandler)($this->storeOnly);

        expect($handler->handleTemplateInclude($this->template))->toBe($this->template);
    });
});

describe('ComingSoonHandler::removeWooCommerceHandler()', function (): void {
    beforeEach(function (): void {
        $this->hookWith = fn (array $callbacks): object => (object) ['callbacks' => [10 => $callbacks]];
    });

    it('does nothing when nothing hooks template_include', function (): void {
        $GLOBALS['wp_filter'] = [];

        ComingSoonHandler::removeWooCommerceHandler();

        expect($GLOBALS['wp_filter'])->toBe([]);
    });

    it('removes the template_include callback of WooCommerce\'s Coming Soon handler', function (): void {
        $handler = new ComingSoonRequestHandler;
        $GLOBALS['wp_filter']['template_include'] = ($this->hookWith)([
            'closure' => ['function' => fn (string $t): string => $t],
            'wc' => ['function' => [$handler, 'handle_template_include']],
        ]);
        Functions\expect('remove_filter')->once()->with('template_include', [$handler, 'handle_template_include'], 10);

        ComingSoonHandler::removeWooCommerceHandler();
    });

    it('leaves other callbacks of template_include in place', function (): void {
        $GLOBALS['wp_filter']['template_include'] = ($this->hookWith)([
            'static' => ['function' => [ComingSoonHandler::class, 'removeWooCommerceHandler']],
            'other class' => ['function' => [new stdClass, 'handle_template_include']],
            'other method' => ['function' => [new ComingSoonRequestHandler, 'other_method']],
            'no function' => [],
        ]);
        Functions\expect('remove_filter')->never();

        ComingSoonHandler::removeWooCommerceHandler();
    });
});
