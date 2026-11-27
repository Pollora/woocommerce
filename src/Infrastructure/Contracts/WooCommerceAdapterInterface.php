<?php

declare(strict_types=1);

namespace Pollora\WooCommerce\Infrastructure\Contracts;

use Pollora\WooCommerce\Domain\Contracts\WooCommerceEnvironmentInterface;

/**
 * What the integration asks of WordPress and WooCommerce, the environment of the domain included.
 */
interface WooCommerceAdapterInterface extends WooCommerceEnvironmentInterface
{
    /**
     * Locate a template using WordPress locate_template function.
     *
     * @param  string|array<string>  $templates  Template file(s) to search for
     * @param  bool  $load  Whether to load the template if found
     * @param  bool  $requireOnce  Whether to require_once or require
     * @return string The located template path
     */
    public function locateTemplate(string|array $templates, bool $load = false, bool $requireOnce = true): string;

    /**
     * Add theme support for WooCommerce.
     *
     * @param  string  $feature  The feature to add support for
     * @param  mixed  $options  Optional feature options
     * @return false|null False on failure, null on success
     */
    public function addThemeSupport(string $feature, mixed $options = null): mixed;

    /**
     * Get the current stylesheet directory.
     *
     * @return string The stylesheet directory path
     */
    public function getStylesheetDirectory(): string;

    /**
     * Check if we're in WordPress admin area.
     *
     * @return bool True if in admin area
     */
    public function isAdmin(): bool;

    /**
     * Check if WordPress is doing AJAX.
     *
     * @return bool True if doing AJAX
     */
    public function isDoingAjax(): bool;

    /**
     * Get current WordPress screen.
     *
     * @return \WP_Screen|null Current screen object or null
     */
    public function getCurrentScreen(): ?\WP_Screen;

    /**
     * Check if currently performing an action.
     *
     * @param  string  $action  The action to check for
     * @return bool True if performing the action
     */
    public function isDoingAction(string $action): bool;

    /**
     * Apply WordPress filters.
     *
     * @param  string  $hook  The name of the filter hook
     * @param  mixed  $value  The value to filter
     * @param  mixed  ...$args  Additional arguments to pass to callbacks
     * @return mixed The filtered value
     */
    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed;

    /**
     * Check if WooCommerce is available and loaded.
     *
     * @return bool True if WooCommerce is available
     */
    public function isWooCommerceAvailable(): bool;
}
