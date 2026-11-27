<?php

declare(strict_types=1);

namespace Pollora\WooCommerce\Infrastructure\Services;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Pollora\WooCommerce\Domain\Contracts\ComingSoonHandlerInterface;

/**
 * Handles WooCommerce Coming Soon page rendering with Blade templates.
 *
 * This handler replaces WooCommerce's default Coming Soon template
 * (which bypasses the theme's Blade rendering pipeline) with a proper
 * Blade template when one exists in the active theme.
 *
 * WooCommerce's ComingSoonRequestHandler hooks into template_include
 * and renders its own block-based template, calling exit() for classic
 * themes. This handler intercepts that behavior by removing WC's hook
 * and providing a Blade-compatible alternative.
 */
final class ComingSoonHandler implements ComingSoonHandlerInterface
{
    /**
     * Blade view name for the coming soon page.
     */
    private const string VIEW_NAME = 'woocommerce.coming-soon';

    /**
     * Blade view name for the store-only coming soon page.
     */
    private const string STORE_ONLY_VIEW_NAME = 'woocommerce.coming-soon-store-only';

    /**
     * Query parameter and cookie WooCommerce's private link carries the share key in.
     */
    private const string SHARE_KEY_NAME = 'woo-share';

    /**
     * How long the share key cookie lasts: 90 days, as WooCommerce sets it.
     */
    private const int SHARE_KEY_COOKIE_LIFETIME = 90 * 24 * 60 * 60;

    public function __construct(
        private readonly ViewFactory $viewFactory
    ) {}

    /**
     * {@inheritDoc}
     */
    public function handleTemplateInclude(string $template): string
    {
        if (! $this->shouldShowComingSoon()) {
            return $template;
        }

        $viewName = $this->resolveViewName();

        if ($viewName === null) {
            return $template;
        }

        // Set cache header matching WooCommerce's behavior
        if (! headers_sent()) {
            header('Cache-Control: max-age=60');
        }

        return $this->getTemplatePathForView($viewName) ?? $template;
    }

    /**
     * Determine if the coming soon page should be shown for the current request.
     */
    private function shouldShowComingSoon(): bool
    {
        if (get_option('woocommerce_coming_soon') !== 'yes') {
            return false;
        }

        // Don't show to users who can manage WooCommerce
        if (function_exists('current_user_can') && current_user_can('manage_woocommerce')) {
            return false;
        }

        // Requests a plugin excludes from Coming Soon mode, as WooCommerce allows
        if (apply_filters('woocommerce_coming_soon_exclude', false)) {
            return false;
        }

        // Check private link / share key access
        if ($this->hasValidShareKeyAccess()) {
            return false;
        }

        $storeOnly = get_option('woocommerce_store_pages_only') === 'yes';

        if ($storeOnly) {
            // Only show on store pages (404 pages excluded)
            if (function_exists('is_404') && is_404()) {
                return false;
            }

            return $this->isStorePage();
        }

        // Whole site coming soon
        return true;
    }

    /**
     * Check if the current request has valid share key access.
     */
    private function hasValidShareKeyAccess(): bool
    {
        if (get_option('woocommerce_private_link') !== 'yes') {
            return false;
        }

        $option = get_option('woocommerce_share_key');
        if (! is_scalar($option) || in_array((string) $option, ['', '0'], true)) {
            return false;
        }

        $shareKey = (string) $option;

        // The private link carries the key as a query parameter: remember it, as
        // WooCommerce does, so the visitor keeps access on the next pages
        if ($this->matchesShareKey($_GET[self::SHARE_KEY_NAME] ?? null, $shareKey)) {
            $this->rememberShareKey($shareKey);

            return true;
        }

        return $this->matchesShareKey($_COOKIE[self::SHARE_KEY_NAME] ?? null, $shareKey);
    }

    /**
     * Compare a submitted share key with the stored one in constant time.
     *
     * A plain `===` stops at the first differing character, which lets an attacker
     * recover the key byte by byte from response timings. A value that is not a
     * string (absent, or an array such as `?woo-share[]=x`) never matches.
     *
     * @param  mixed  $submitted  Raw value read from the query string or the cookie
     * @param  string  $shareKey  The non-empty key stored by WooCommerce
     */
    private function matchesShareKey(mixed $submitted, string $shareKey): bool
    {
        return is_string($submitted) && hash_equals($shareKey, $submitted);
    }

    /**
     * Persist the share key in the cookie WooCommerce reads it back from.
     */
    private function rememberShareKey(string $shareKey): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(self::SHARE_KEY_NAME, $shareKey, [
            'expires' => time() + self::SHARE_KEY_COOKIE_LIFETIME,
            'path' => '/',
        ]);
    }

    /**
     * Determine if the current page is a WooCommerce store page.
     */
    private function isStorePage(): bool
    {
        if (! function_exists('is_woocommerce')) {
            return false;
        }

        if (is_woocommerce()
            || (function_exists('is_cart') && is_cart())
            || (function_exists('is_checkout') && is_checkout())
            || (function_exists('is_account_page') && is_account_page())) {
            return true;
        }

        // Check shop page
        if (function_exists('is_shop') && is_shop()) {
            return true;
        }

        if (! function_exists('wc_terms_and_conditions_page_id')) {
            return false;
        }

        $termsPageId = wc_terms_and_conditions_page_id();

        return $termsPageId > 0 && is_page($termsPageId);
    }

    /**
     * Resolve which Blade view to use for coming soon.
     */
    private function resolveViewName(): ?string
    {
        $storeOnly = get_option('woocommerce_store_pages_only') === 'yes';

        // Try store-only variant first if applicable
        if ($storeOnly && $this->viewFactory->exists(self::STORE_ONLY_VIEW_NAME)) {
            return self::STORE_ONLY_VIEW_NAME;
        }

        // Fall back to generic coming soon template
        if ($this->viewFactory->exists(self::VIEW_NAME)) {
            return self::VIEW_NAME;
        }

        return null;
    }

    /**
     * Get the file path for a Blade view so FrontendController can render it.
     */
    private function getTemplatePathForView(string $viewName): ?string
    {
        try {
            /** @phpstan-ignore method.notFound */
            return $this->viewFactory->make($viewName)->getPath();
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Remove WooCommerce's native ComingSoonRequestHandler from template_include.
     *
     * WC's handler calls exit() for classic themes, which would prevent
     * the Blade rendering pipeline from working.
     */
    public static function removeWooCommerceHandler(): void
    {
        global $wp_filter;

        if (! isset($wp_filter['template_include'])) {
            return;
        }

        foreach ($wp_filter['template_include']->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (! is_array($callback['function'] ?? null)) {
                    continue;
                }

                $fn = $callback['function'];
                if (
                    is_object($fn[0])
                    && str_ends_with($fn[0]::class, 'ComingSoonRequestHandler')
                    && ($fn[1] ?? '') === 'handle_template_include'
                ) {
                    remove_filter('template_include', $fn, $priority);

                    return;
                }
            }
        }
    }
}
