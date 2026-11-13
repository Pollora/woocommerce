<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Contracts\Events\Dispatcher;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\HookInterface;
use Pollora\WooCommerce\Events\AttributeCreated;
use Pollora\WooCommerce\Events\AttributeDeleted;
use Pollora\WooCommerce\Events\AttributeUpdated;
use Pollora\WooCommerce\Events\OrderStatusChanged;
use Pollora\WooCommerce\Events\SettingUpdated;
use Pollora\WooCommerce\Events\TaxRateCreated;
use Pollora\WooCommerce\Events\TaxRateDeleted;
use Pollora\WooCommerce\Events\TaxRateUpdated;
use Pollora\WooCommerce\Events\WooCommerceEventDispatcher;

beforeEach(function (): void {
    $this->events = Mockery::mock(Dispatcher::class);
    $this->action = Mockery::mock(Action::class);
    $this->dispatcher = new WooCommerceEventDispatcher($this->events, $this->action);
});

describe('WooCommerceEventDispatcher::registerEvents()', function (): void {
    it('listens to each WooCommerce action with the handler named after it', function (): void {
        $registered = [];
        $this->action->shouldReceive('add')->times(8)->andReturnUsing(
            function (string $hook, array $callback) use (&$registered): HookInterface {
                $registered[$hook] = $callback[1];

                return Mockery::mock(HookInterface::class);
            }
        );

        $this->dispatcher->registerEvents();

        expect($registered)->toBe([
            'woocommerce_order_status_changed' => 'handleWoocommerceOrderStatusChanged',
            'woocommerce_attribute_added' => 'handleWoocommerceAttributeAdded',
            'woocommerce_attribute_updated' => 'handleWoocommerceAttributeUpdated',
            'woocommerce_attribute_deleted' => 'handleWoocommerceAttributeDeleted',
            'woocommerce_tax_rate_added' => 'handleWoocommerceTaxRateAdded',
            'woocommerce_tax_rate_updated' => 'handleWoocommerceTaxRateUpdated',
            'woocommerce_tax_rate_deleted' => 'handleWoocommerceTaxRateDeleted',
            'updated_option' => 'handleUpdatedOption',
        ]);

        foreach ($registered as $method) {
            expect(method_exists($this->dispatcher, $method))->toBeTrue();
        }
    });
});

describe('WooCommerceEventDispatcher handlers', function (): void {
    it('dispatches OrderStatusChanged with the order and both statuses', function (): void {
        $order = new WC_Order;
        Functions\expect('wc_get_order')->once()->with(42)->andReturn($order);
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::on(
            fn (object $event): bool => $event instanceof OrderStatusChanged
                && $event->order === $order
                && $event->oldStatus === 'pending'
                && $event->newStatus === 'completed'
        ));

        $this->dispatcher->handleWoocommerceOrderStatusChanged(42, 'pending', 'completed');
    });

    it('dispatches nothing when the order cannot be loaded', function (): void {
        Functions\expect('wc_get_order')->once()->with(42)->andReturn(false);
        $this->events->shouldNotReceive('dispatch');

        $this->dispatcher->handleWoocommerceOrderStatusChanged(42, 'pending', 'completed');
    });

    it('dispatches AttributeCreated with the attribute id and data', function (): void {
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::on(
            fn (object $event): bool => $event instanceof AttributeCreated
                && $event->attributeId === 7
                && $event->attribute === ['name' => 'Colour']
        ));

        $this->dispatcher->handleWoocommerceAttributeAdded(7, ['name' => 'Colour']);
    });

    it('dispatches AttributeUpdated with the attribute id and data', function (): void {
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::on(
            fn (object $event): bool => $event instanceof AttributeUpdated
                && $event->attributeId === 7
                && $event->attribute === ['name' => 'Size']
        ));

        $this->dispatcher->handleWoocommerceAttributeUpdated(7, ['name' => 'Size']);
    });

    it('dispatches AttributeDeleted with the attribute id and name', function (): void {
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::on(
            fn (object $event): bool => $event instanceof AttributeDeleted
                && $event->attributeId === 7
                && $event->attributeName === 'colour'
        ));

        $this->dispatcher->handleWoocommerceAttributeDeleted(7, 'colour');
    });

    it('dispatches TaxRateCreated with the tax rate id and data', function (): void {
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::on(
            fn (object $event): bool => $event instanceof TaxRateCreated
                && $event->taxRateId === 3
                && $event->taxRate === ['tax_rate' => '20.0000']
        ));

        $this->dispatcher->handleWoocommerceTaxRateAdded(3, ['tax_rate' => '20.0000']);
    });

    it('dispatches TaxRateUpdated with the tax rate id and data', function (): void {
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::on(
            fn (object $event): bool => $event instanceof TaxRateUpdated
                && $event->taxRateId === 3
                && $event->taxRate === ['tax_rate' => '5.5000']
        ));

        $this->dispatcher->handleWoocommerceTaxRateUpdated(3, ['tax_rate' => '5.5000']);
    });

    it('dispatches TaxRateDeleted with the tax rate id', function (): void {
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::on(
            fn (object $event): bool => $event instanceof TaxRateDeleted && $event->taxRateId === 3
        ));

        $this->dispatcher->handleWoocommerceTaxRateDeleted(3);
    });

    it('dispatches SettingUpdated for a WooCommerce option', function (): void {
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::on(
            fn (object $event): bool => $event instanceof SettingUpdated
                && $event->optionName === 'woocommerce_currency'
                && $event->oldValue === 'EUR'
                && $event->newValue === 'USD'
        ));

        $this->dispatcher->handleUpdatedOption('woocommerce_currency', 'EUR', 'USD');
    });

    it('ignores the update of an option WooCommerce does not own', function (): void {
        $this->events->shouldNotReceive('dispatch');

        $this->dispatcher->handleUpdatedOption('blogname', 'Old', 'New');
    });
});
