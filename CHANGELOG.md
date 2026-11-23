# Changelog

All notable changes to `pollora/woocommerce` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- The service provider adds the shopper's session groups, `wc_session_id` (and the group `WC_SESSION_CACHE_GROUP` names, when the project renamed it) and `wc_cache_*`, to `pollora.object-cache.prefetch.exclude_groups`. With `pollora/object-cache` 0.2 and its prefetch on, a cart is then never loaded before the request asks for it. Nothing changes, and nothing is required, without that package. `pollora/object-cache` is listed in `suggest`.

### Changed

- The event classes (`AttributeCreated`, `AttributeDeleted`, `AttributeUpdated`, `OrderStatusChanged`, `SettingUpdated`, `TaxRateCreated`, `TaxRateDeleted`, `TaxRateUpdated`), `ComingSoonHandler`, `WooCommerce`, `WooCommerceTemplateResolver` and `WooCommerceServiceProvider` are now `final` (CONV-08). This is a restriction of the API for anyone who extended them: a class that is not an extension point can no longer be subclassed; compose them instead
- **BREAKING**: `RegisterWooCommerceHooksUseCase` and `WooCommerceEventDispatcher` are now `final` (CONV-08). Nothing in the ecosystem extends them; anyone who subclassed one must compose it instead (inject it, or listen to the same WordPress hooks). `WooCommerceService` and `WordPressWooCommerceAdapter` stay open for now: the unit tests mock them and no interface exists yet to mock instead

## [0.1.3] - 2026-11-19

### Security

- The share key of the private coming soon link is compared in constant time (`hash_equals()`), so response timings cannot be used to recover it. A value that is not a string (such as `?woo-share[]=x`) never grants access.

## [0.1.2] - 2026-11-16

### Fixed

- The theme's Blade templates and the WooCommerce events also apply to the requests WordPress answers on its own: REST, Store API checkout, `?wc-api=` callbacks such as a payment webhook. The framework runs `wp()` from its own boot and these requests end there, before this provider booted and hooked in: an email sent while handling one used WooCommerce's own header and footer. The hooks are now registered while the application is booting, before any provider boots.

### Changed

- The architecture rules (`tests/Arch.php`) now run as the `Architecture` suite of `phpunit.xml.dist`.

## [0.1.1] - 2026-11-03

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
