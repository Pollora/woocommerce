<?php

declare(strict_types=1);

/*
 * The harness's preset covers WordPress's classes and functions.
 * WooCommerce is a plugin, not a Composer dependency: its classes never autoload here.
 */
return (require __DIR__.'/vendor/pollora/harness/presets/composer-dependency-analyser.php')
    ->ignoreUnknownClasses(['WC_Order']);
