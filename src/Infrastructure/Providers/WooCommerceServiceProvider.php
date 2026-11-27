<?php

declare(strict_types=1);

namespace Pollora\WooCommerce\Infrastructure\Providers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;
use Pollora\WooCommerce\Application\UseCases\RegisterWooCommerceHooksUseCase;
use Pollora\WooCommerce\Domain\Contracts\ComingSoonHandlerInterface;
use Pollora\WooCommerce\Domain\Contracts\TemplateResolverInterface;
use Pollora\WooCommerce\Domain\Contracts\WooCommerceEnvironmentInterface;
use Pollora\WooCommerce\Domain\Contracts\WooCommerceIntegrationInterface;
use Pollora\WooCommerce\Domain\Contracts\WooCommerceServiceInterface;
use Pollora\WooCommerce\Domain\Services\WooCommerceService;
use Pollora\WooCommerce\Events\WooCommerceEventDispatcher;
use Pollora\WooCommerce\Infrastructure\Adapters\WordPressWooCommerceAdapter;
use Pollora\WooCommerce\Infrastructure\Contracts\WooCommerceAdapterInterface;
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
final class WooCommerceServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerRouteConditions();
        $this->excludeSessionsFromPrefetch();
        $this->registerDomainServices();
        $this->registerInfrastructureServices();
        $this->registerApplicationServices();

        $this->app->booting($this->registerWordPressHooks(...));
    }

    /**
     * Hook into WordPress before any service provider boots.
     *
     * The framework runs WordPress from its own boot, `wp()` included: a REST
     * request, or a `?wc-api=` callback such as a payment webhook, is answered
     * and ends there. Hooks added from this provider's boot would come too late
     * for it, and an email sent while handling it would fall back to
     * WooCommerce's own templates.
     */
    private function registerWordPressHooks(): void
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
        $config = $this->config();

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
     * Keep the shopper's session out of the object cache prefetch.
     *
     * With `pollora/object-cache` and its prefetch on, a page loads the keys its kind of page read
     * before, at the first read of the request. WooCommerce's session group (`wc_session_id`,
     * `WC_SESSION_CACHE_GROUP`) and the cache prefixes it builds from group names (`wc_cache_*`)
     * hold a visitor's cart: a value read too early could show a cart another request just changed.
     * The groups are added to the package's `prefetch.exclude_groups` setting, which is only an
     * array in the configuration, so nothing happens, and nothing is required, without the package.
     */
    private function excludeSessionsFromPrefetch(): void
    {
        $config = $this->config();
        $groups = ['wc_session_id', 'wc_cache_*'];

        $sessionGroup = defined('WC_SESSION_CACHE_GROUP') ? constant('WC_SESSION_CACHE_GROUP') : null;

        if (is_string($sessionGroup)) {
            $groups[] = $sessionGroup;
        }

        $current = $config->get('pollora.object-cache.prefetch.exclude_groups', []);

        $config->set('pollora.object-cache.prefetch.exclude_groups', array_values(array_unique([
            ...(is_array($current) ? array_filter($current, is_string(...)) : []),
            ...$groups,
        ])));
    }

    /**
     * The configuration repository of the application.
     *
     * @throws LogicException If 'config' is bound to something else.
     */
    private function config(): Repository
    {
        $config = $this->app->make('config');

        return $config instanceof Repository ? $config : throw new LogicException('The "config" binding of the application is not a configuration repository.');
    }

    /**
     * Register domain layer services.
     */
    private function registerDomainServices(): void
    {
        // Autowired: the container hands it the bound WooCommerceEnvironmentInterface
        $this->app->singleton(WooCommerceService::class);
        $this->app->singleton(WooCommerceServiceInterface::class, fn (Container $app): WooCommerceService => $app->make(WooCommerceService::class));
        $this->app->singleton(WooCommerceEventDispatcher::class);
    }

    /**
     * Register infrastructure layer services.
     */
    private function registerInfrastructureServices(): void
    {
        // Register the WordPress adapter
        $this->app->singleton(WordPressWooCommerceAdapter::class);
        $this->app->singleton(WooCommerceAdapterInterface::class, fn (Container $app): WordPressWooCommerceAdapter => $app->make(WordPressWooCommerceAdapter::class));
        $this->app->singleton(WooCommerceEnvironmentInterface::class, fn (Container $app): WooCommerceAdapterInterface => $app->make(WooCommerceAdapterInterface::class));

        // Register the main WooCommerce integration implementation
        $this->app->singleton(WooCommerceIntegrationInterface::class, fn (Container $app): WooCommerce => new WooCommerce(
            $app->make(TemplateFinderInterface::class),
            $app->make(ViewFactory::class),
            $app->make(WooCommerceServiceInterface::class),
            $app->make(WooCommerceAdapterInterface::class)
        ));

        // Register the template resolver implementation
        $this->app->singleton(TemplateResolverInterface::class, fn (Container $app): WooCommerceTemplateResolver => new WooCommerceTemplateResolver(
            $app->make(WooCommerceServiceInterface::class)
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
