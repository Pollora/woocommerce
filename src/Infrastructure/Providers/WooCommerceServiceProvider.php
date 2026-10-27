<?php

declare(strict_types=1);

namespace Pollora\WooCommerce\Infrastructure\Providers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\ServiceProvider;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;
use Pollora\WooCommerce\Application\UseCases\RegisterWooCommerceHooksUseCase;
use Pollora\WooCommerce\Domain\Contracts\ComingSoonHandlerInterface;
use Pollora\WooCommerce\Domain\Contracts\TemplateResolverInterface;
use Pollora\WooCommerce\Domain\Contracts\WooCommerceEnvironmentInterface;
use Pollora\WooCommerce\Domain\Contracts\WooCommerceIntegrationInterface;
use Pollora\WooCommerce\Domain\Services\WooCommerceService;
use Pollora\WooCommerce\Events\WooCommerceEventDispatcher;
use Pollora\WooCommerce\Infrastructure\Adapters\WordPressWooCommerceAdapter;
use Pollora\WooCommerce\Infrastructure\Services\ComingSoonHandler;
use Pollora\WooCommerce\Infrastructure\Services\WooCommerce;
use Pollora\WooCommerce\Infrastructure\Services\WooCommerceTemplateResolver;

/**
 * WooCommerce service provider following hexagonal architecture.
 *
 * This service provider registers all WooCommerce-related services
 * and binds interfaces to their implementations according to the
 * hexagonal architecture pattern.
 */
class WooCommerceServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerRouteConditions();
        $this->registerDomainServices();
        $this->registerInfrastructureServices();
        $this->registerApplicationServices();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->executeWooCommerceHooksRegistration();
        $this->app->make(WooCommerceEventDispatcher::class)->registerEvents();
    }

    /**
     * Give `Route::wp()` the WooCommerce conditions (`shop`, `product`, `cart`…).
     *
     * A project's own `wordpress.plugin_conditions.woocommerce` wins over these defaults.
     */
    private function registerRouteConditions(): void
    {
        $config = $this->app->make('config');

        $config->set('wordpress.plugin_conditions.woocommerce', array_merge([
            'is_shop' => 'shop',
            'is_product' => 'product',
            'is_cart' => 'cart',
            'is_checkout' => 'checkout',
            'is_account_page' => 'account',
            'is_product_category' => 'product_category',
            'is_product_tag' => 'product_tag',
            'is_wc_endpoint_url' => 'wc_endpoint',
        ], (array) $config->get('wordpress.plugin_conditions.woocommerce', [])));
    }

    /**
     * Register domain layer services.
     */
    private function registerDomainServices(): void
    {
        // Autowired: the container hands it the bound WooCommerceEnvironmentInterface
        $this->app->singleton(WooCommerceService::class);
        $this->app->singleton(WooCommerceEventDispatcher::class);
    }

    /**
     * Register infrastructure layer services.
     */
    private function registerInfrastructureServices(): void
    {
        // Register the WordPress adapter
        $this->app->singleton(WordPressWooCommerceAdapter::class);
        $this->app->singleton(WooCommerceEnvironmentInterface::class, fn (Container $app): WordPressWooCommerceAdapter => $app->make(WordPressWooCommerceAdapter::class));

        // Register the main WooCommerce integration implementation
        $this->app->singleton(WooCommerceIntegrationInterface::class, fn (Container $app): WooCommerce => new WooCommerce(
            $app->make(TemplateFinderInterface::class),
            $app->make(ViewFactory::class),
            $app->make(WooCommerceService::class),
            $app->make(WordPressWooCommerceAdapter::class)
        ));

        // Register the template resolver implementation
        $this->app->singleton(TemplateResolverInterface::class, fn (Container $app): WooCommerceTemplateResolver => new WooCommerceTemplateResolver(
            $app->make(WooCommerceService::class)
        ));

        // Register the Coming Soon handler
        $this->app->singleton(ComingSoonHandlerInterface::class, fn (Container $app): ComingSoonHandler => new ComingSoonHandler(
            $app->make(ViewFactory::class)
        ));

        // Maintain backward compatibility by binding the old class name
        $this->app->singleton('Pollora\\ThirdParty\\WooCommerce\\WooCommerce', fn (Container $app) => $app->make(WooCommerceIntegrationInterface::class));

        $this->app->singleton('Pollora\\ThirdParty\\WooCommerce\\View\\WooCommerceTemplateResolver', fn (Container $app) => $app->make(TemplateResolverInterface::class));
    }

    /**
     * Register application layer services.
     */
    private function registerApplicationServices(): void
    {
        $this->app->singleton(RegisterWooCommerceHooksUseCase::class, fn (Container $app): RegisterWooCommerceHooksUseCase => new RegisterWooCommerceHooksUseCase(
            $app->make(Action::class),
            $app->make(Filter::class),
            $app->make(WooCommerceIntegrationInterface::class),
            $app->make(TemplateResolverInterface::class),
            $app->make(ComingSoonHandlerInterface::class)
        ));
    }

    /**
     * Execute the WooCommerce hooks registration use case.
     */
    private function executeWooCommerceHooksRegistration(): void
    {
        $useCase = $this->app->make(RegisterWooCommerceHooksUseCase::class);
        $useCase->execute();
    }
}
