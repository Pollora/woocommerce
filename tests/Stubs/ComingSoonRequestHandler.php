<?php

declare(strict_types=1);

namespace Automattic\WooCommerce\Internal\ComingSoon;

/**
 * Stand-in for WooCommerce's Coming Soon handler: the package finds it by the end of its class name
 * and the name of its `template_include` method (taken from the package's code, not from WooCommerce's sources).
 */
class ComingSoonRequestHandler
{
    public function handle_template_include(string $template): string
    {
        return $template;
    }

    public function other_method(string $template): string
    {
        return $template;
    }
}
