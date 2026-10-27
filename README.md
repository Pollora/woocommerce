<p align="center">
  <a href="https://packagist.org/packages/pollora/woocommerce"><img src="https://img.shields.io/packagist/v/pollora/woocommerce" alt="Latest version"></a>
  <a href="https://github.com/Pollora/woocommerce/actions/workflows/harness.yml"><img src="https://github.com/Pollora/woocommerce/actions/workflows/harness.yml/badge.svg" alt="Harness"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/Pollora/woocommerce" alt="License"></a>
</p>

# Pollora WooCommerce

WooCommerce for [Pollora](https://pollora.dev), the Laravel framework for WordPress: write the shop's templates in Blade, answer `Route::wp('shop')` and `Route::wp('product')`, render the Coming Soon page from your theme, and listen to WooCommerce's orders, taxes and attributes as Laravel events.

> This used to live in the framework, as `Pollora\ThirdParty\WooCommerce`. The old class names still resolve, to this package.

## Installation

```bash
composer require pollora/woocommerce
```

Requires PHP 8.3+, [`pollora/framework`](https://github.com/Pollora/framework) 13.39+ and WooCommerce. The service provider is discovered by Laravel; nothing runs unless WooCommerce is active.

## Blade templates

WooCommerce looks for a template in the theme, then in its own folder. The package adds the Blade variant of each candidate to the search, so `resources/views/woocommerce/single-product.blade.php` answers where `woocommerce/single-product.php` would, and a child theme's override folder is honoured. Template parts (`wc_get_template_part`) and `wc_get_template` resolve the same way.

Filter `pollora/woocommerce/template_paths` to add a folder to the search.

## Route conditions

`Route::wp()` knows WooCommerce's conditionals:

```php
Route::wp('shop', [ShopController::class, 'index']);
Route::wp('product', [ProductController::class, 'show']);
```

| Alias | Condition |
| --- | --- |
| `shop` | `is_shop` |
| `product` | `is_product` |
| `cart` | `is_cart` |
| `checkout` | `is_checkout` |
| `account` | `is_account_page` |
| `product_category` | `is_product_category` |
| `product_tag` | `is_product_tag` |
| `wc_endpoint` | `is_wc_endpoint_url` |

A project's own `wordpress.plugin_conditions.woocommerce` entries win over these defaults.

## Coming Soon page

WooCommerce renders its Coming Soon page itself and exits, which skips Blade. With a view `woocommerce.coming-soon` in your theme (or `woocommerce.coming-soon-store-only` when only the store pages are hidden), the package renders that instead.

## Events

WooCommerce's actions arrive as Laravel events, in `Pollora\WooCommerce\Events`:

| Event | From |
| --- | --- |
| `OrderStatusChanged` | `woocommerce_order_status_changed` |
| `AttributeCreated`, `AttributeUpdated`, `AttributeDeleted` | `woocommerce_attribute_*` |
| `TaxRateCreated`, `TaxRateUpdated`, `TaxRateDeleted` | `woocommerce_tax_rate_*` |
| `SettingUpdated` | WooCommerce options (`updated_option`) |

```php
Event::listen(OrderStatusChanged::class, function (OrderStatusChanged $event): void {
    $event->order;      // WC_Order
    $event->newStatus;
});
```

## Coming from the framework

The classes moved:

| Was | Is |
| --- | --- |
| `Pollora\ThirdParty\WooCommerce\…` | `Pollora\WooCommerce\…` |
| `Pollora\Events\WordPress\Plugins\WooCommerce\…` | `Pollora\WooCommerce\Events\…` |

The old names keep working and are removed in the next major release. Install the package: a framework that no longer ships WooCommerce does nothing for it without this one.

## License

MIT. See [LICENSE](LICENSE).
