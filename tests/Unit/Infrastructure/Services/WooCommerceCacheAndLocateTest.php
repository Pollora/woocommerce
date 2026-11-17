<?php

declare(strict_types=1);

use Illuminate\Contracts\View\Factory as ViewFactory;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;
use Pollora\WooCommerce\Domain\Models\Template;
use Pollora\WooCommerce\Domain\Services\WooCommerceService;
use Pollora\WooCommerce\Infrastructure\Adapters\WordPressWooCommerceAdapter;
use Pollora\WooCommerce\Infrastructure\Services\WooCommerce;

beforeEach(function (): void {
    $this->templateFinder = Mockery::mock(TemplateFinderInterface::class);
    $this->viewFactory = Mockery::mock(ViewFactory::class);
    $this->domainService = Mockery::mock(WooCommerceService::class);
    $this->adapter = Mockery::mock(WordPressWooCommerceAdapter::class);

    $this->wc = new WooCommerce($this->templateFinder, $this->viewFactory, $this->domainService, $this->adapter);

    $this->domainService->shouldReceive('getWooCommerceTemplatePath')->andReturn('woocommerce/');
    $this->domainService->shouldReceive('getAllTemplatePaths')->andReturn([]);
    $this->located = [];
    $this->domainService->shouldReceive('createTemplate')->andReturnUsing(function (string $path): Template {
        $this->located[] = $path;

        return Template::fromPath('/'.$path);
    });
});

describe('WooCommerce::template() cache key', function (): void {
    it('resolves again when the template or the name differs, even if they concatenate alike', function (array $first, array $second): void {
        $this->templateFinder->shouldReceive('locate')->twice()->andReturn([]);

        expect($this->wc->template(...$first))->toBe($first[0])
            ->and($this->wc->template(...$second))->toBe($second[0]);
    })->with([
        'same template, other name' => [['alpha', 'x'], ['alpha', 'y']],
        'name moved into the template' => [['ab', 'c'], ['a', 'bc']],
        'template and name swapped around' => [['ab', 'c'], ['b', 'ca']],
    ]);

    it('resolves once for the same template and name', function (): void {
        $this->templateFinder->shouldReceive('locate')->once()->andReturn([]);

        $this->wc->template('alpha', 'x');
        $this->wc->template('alpha', 'x');
    });
});

describe('WooCommerce::template() choice of the template to locate', function (): void {
    it('locates the template itself when it is a path or a php file, whatever the name', function (string $template): void {
        $this->templateFinder->shouldReceive('locate')->andReturn([]);

        expect($this->wc->template($template, 'name'))->toBe($template)
            ->and($this->located)->toBe([$template]);
    })->with([
        'a path without extension' => ['content/single-product'],
        'a php file without a path' => ['single-product.php'],
    ]);
});

describe('WooCommerce::template() with a file that is not a Blade view', function (): void {
    it('returns that file without looking for a view', function (): void {
        $file = sys_get_temp_dir().'/pollora-wc-'.bin2hex(random_bytes(6)).'.php';
        touch($file);

        try {
            $this->templateFinder->shouldReceive('locate')->andReturn(['woocommerce/single-product.php']);
            $this->adapter->shouldReceive('locateTemplate')->andReturn($file);
            $this->adapter->shouldReceive('isAdmin')->andReturn(false);
            $this->adapter->shouldReceive('isDoingAjax')->andReturn(false);
            $this->adapter->shouldReceive('getCurrentScreen')->andReturn(null);
            $this->domainService->shouldReceive('isWooCommerceStatusScreen')->andReturn(false);
            $this->templateFinder->shouldReceive('getViewNameFromPath')->never();
            $this->viewFactory->shouldReceive('exists')->never();
            $this->viewFactory->shouldReceive('make')->never();

            expect($this->wc->template('/wc/templates/single-product.php'))->toBe($file);
        } finally {
            unlink($file);
        }
    });
});
