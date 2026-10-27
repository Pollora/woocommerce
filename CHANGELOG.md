# Changelog

All notable changes to `pollora/woocommerce` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- WooCommerce's integration with Pollora, as a package of its own: Blade template resolution, the Coming Soon page, and the WooCommerce events (`OrderStatusChanged`, `Attribute*`, `TaxRate*`, `SettingUpdated`). It was `Pollora\ThirdParty\WooCommerce` and `Pollora\Events\WordPress\Plugins\WooCommerce` in `pollora/framework`.
- The service provider gives `Route::wp()` the WooCommerce conditions (`shop`, `product`, `cart`, `checkout`, `account`, `product_category`, `product_tag`, `wc_endpoint`); a project's own `wordpress.plugin_conditions.woocommerce` entries win over them.
- The class names the framework used to ship still resolve, aliased to this package's: `Pollora\ThirdParty\WooCommerce\…` and `Pollora\Events\WordPress\Plugins\WooCommerce\…`. They are removed in the next major release.

### Changed

- The namespace is `Pollora\WooCommerce\`, and the events are in `Pollora\WooCommerce\Events\`.
- The package replaces the 2023 `pollen/woocommerce` prototype that this repository held. It is licensed MIT, like the rest of Pollora.
