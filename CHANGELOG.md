# Changelog

All notable changes to `pollora/woocommerce` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- WooCommerce's private link grants access to the pages the Coming Soon handler hides (shop, product, cart, checkout, account). The handler read a `woocommerce_share_key` cookie, the name of the option holding the key; it now reads the `woo-share` query parameter and cookie as WooCommerce does, and sets the cookie when the key comes from the link, since WooCommerce's own handler, which set it, is removed. ([#7](https://github.com/Pollora/woocommerce/issues/7))
- The Coming Soon handler honours the `woocommerce_coming_soon_exclude` filter, as WooCommerce's handler does.

## [0.1.0] - 2026-10-27

### Added

- WooCommerce's integration with Pollora, as a package of its own: Blade template resolution, the Coming Soon page, and the WooCommerce events (`OrderStatusChanged`, `Attribute*`, `TaxRate*`, `SettingUpdated`). It was `Pollora\ThirdParty\WooCommerce` and `Pollora\Events\WordPress\Plugins\WooCommerce` in `pollora/framework`.
- The service provider gives `Route::wp()` the WooCommerce conditions (`shop`, `product`, `cart`, `checkout`, `account`, `product_category`, `product_tag`, `wc_endpoint`); a project's own `wordpress.plugin_conditions.woocommerce` entries win over them.
- The class names the framework used to ship still resolve, aliased to this package's: `Pollora\ThirdParty\WooCommerce\…` and `Pollora\Events\WordPress\Plugins\WooCommerce\…`. They are removed in the next major release.
- The hooks the package registers on `plugins_loaded`, `wp_loaded` and `after_setup_theme` run at once when WordPress has already fired them. The framework loads WordPress while it boots, before a package's service provider boots, so without this the shop kept WooCommerce's own templates instead of the theme's Blade views.

### Changed

- The namespace is `Pollora\WooCommerce\`, and the events are in `Pollora\WooCommerce\Events\`.
- The package replaces the 2023 `pollen/woocommerce` prototype that this repository held. It is licensed MIT, like the rest of Pollora.
