<?php

declare(strict_types=1);

namespace Pollora\WooCommerce\Domain\Contracts;

use Pollora\WooCommerce\Domain\Models\Template;

/**
 * The template rules of the WooCommerce integration: where its templates are, and their Blade names.
 */
interface WooCommerceServiceInterface
{
    /**
     * Get default WooCommerce template paths.
     *
     * @return array<string> List of default template paths
     */
    public function getDefaultTemplatePaths(): array;

    /**
     * Get theme template paths for WooCommerce.
     *
     * @return array<string> List of theme template paths
     */
    public function getThemeTemplatePaths(): array;

    /**
     * Check if current screen matches WooCommerce status screen.
     *
     * @param  bool  $isAdmin  Whether currently in admin area
     * @param  bool  $isDoingAjax  Whether currently doing AJAX
     * @param  object|null  $currentScreen  Current screen object
     * @return bool True if on WooCommerce status screen
     */
    public function isWooCommerceStatusScreen(bool $isAdmin, bool $isDoingAjax, ?object $currentScreen): bool;

    /**
     * Get WooCommerce template path prefix.
     *
     * @return string The template path prefix (usually 'woocommerce/')
     */
    public function getWooCommerceTemplatePath(): string;

    /**
     * Get all template paths (default + theme).
     *
     * @return array<string> Complete list of template paths
     */
    public function getAllTemplatePaths(): array;

    /**
     * Create a Template instance with WooCommerce context.
     *
     * @param  string  $templatePath  The template file path
     * @return Template The template instance
     */
    public function createTemplate(string $templatePath): Template;

    /**
     * Convert PHP template names to Blade equivalents in a list.
     *
     * @param  array<string>  $templates  List of template names
     * @return array<string> List with Blade template variants added
     */
    public function addBladeVariants(array $templates): array;
}
