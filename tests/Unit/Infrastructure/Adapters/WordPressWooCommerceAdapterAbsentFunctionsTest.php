<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\WooCommerce\Infrastructure\Adapters\WordPressWooCommerceAdapter;

/*
 * A function Brain Monkey defines stays defined for the rest of the process, so these tests
 * live in a file that sorts before the one that defines them, and stand aside if a random
 * order ran them after a definition.
 */
describe('WordPressWooCommerceAdapter without WordPress functions', function (): void {
    beforeEach(function (): void {
        $this->adapter = new WordPressWooCommerceAdapter;
    });

    test('gives an empty stylesheet directory', function (): void {
        expect($this->adapter->getStylesheetDirectory())->toBe('');
    })->skip(fn (): bool => function_exists('get_stylesheet_directory'), 'a random order defined the function first');

    test('gives an empty template directory', function (): void {
        expect($this->adapter->getTemplateDirectory())->toBe('');
    })->skip(fn (): bool => function_exists('get_template_directory'), 'a random order defined the function first');

    test('is not in the admin area', function (): void {
        expect($this->adapter->isAdmin())->toBeFalse();
    })->skip(fn (): bool => function_exists('is_admin'), 'a random order defined the function first');

    test('is not doing ajax', function (): void {
        expect($this->adapter->isDoingAjax())->toBeFalse();
    })->skip(fn (): bool => function_exists('wp_doing_ajax'), 'a random order defined the function first');

    test('reports WooCommerce unavailable when only its path constant is defined', function (): void {
        if (! defined('WC_ABSPATH')) {
            define('WC_ABSPATH', '/var/www/html/wp-content/plugins/woocommerce/');
        }

        expect($this->adapter->isWooCommerceAvailable())->toBeFalse();
    })->skip(fn (): bool => function_exists('WC'), 'a random order defined the function first');

    test('reports WooCommerce available when its path constant and its function exist', function (): void {
        if (! defined('WC_ABSPATH')) {
            define('WC_ABSPATH', '/var/www/html/wp-content/plugins/woocommerce/');
        }
        Functions\when('WC')->justReturn(new stdClass);

        expect($this->adapter->isWooCommerceAvailable())->toBeTrue();
    });
});

describe('WordPressWooCommerceAdapter when WordPress says no', function (): void {
    beforeEach(function (): void {
        $this->adapter = new WordPressWooCommerceAdapter;
    });

    test('follows is_admin when it says no', function (): void {
        Functions\when('is_admin')->justReturn(false);

        expect($this->adapter->isAdmin())->toBeFalse();
    });

    test('follows wp_doing_ajax when it says no', function (): void {
        Functions\when('wp_doing_ajax')->justReturn(false);

        expect($this->adapter->isDoingAjax())->toBeFalse();
    });

    test('follows doing_action when it says no', function (): void {
        Functions\when('doing_action')->justReturn(false);

        expect($this->adapter->isDoingAction('init'))->toBeFalse();
    });
});
