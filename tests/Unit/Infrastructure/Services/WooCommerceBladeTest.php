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

    $this->tmp = sys_get_temp_dir().'/pollora-wc-'.bin2hex(random_bytes(6));
    mkdir($this->tmp);
    $this->bladeFile = $this->tmp.'/single-product.blade.php';
    touch($this->bladeFile);

    // A theme template is found for the requested WooCommerce template
    $this->findThemeTemplate = function (string $found): void {
        $this->domainService->shouldReceive('getWooCommerceTemplatePath')->andReturn('woocommerce/');
        $this->domainService->shouldReceive('createTemplate')->andReturn(Template::fromPath('/wc/templates/single-product.php'));
        $this->domainService->shouldReceive('getAllTemplatePaths')->andReturn(['/wc/templates/']);
        $this->templateFinder->shouldReceive('locate')->with('woocommerce/single-product.php')->andReturn(['woocommerce/single-product.blade.php']);
        $this->adapter->shouldReceive('locateTemplate')->with(['woocommerce/single-product.blade.php'])->andReturn($found);
        $this->adapter->shouldReceive('isAdmin')->andReturn(false);
        $this->adapter->shouldReceive('isDoingAjax')->andReturn(false);
        $this->adapter->shouldReceive('getCurrentScreen')->andReturn(null);
    };
});

afterEach(function (): void {
    if (is_file($this->bladeFile)) {
        unlink($this->bladeFile);
    }
    if (is_dir($this->tmp)) {
        rmdir($this->tmp);
    }
});

describe('WooCommerce theme setup', function (): void {
    it('loads the theme template hooks file once, with require_once', function (): void {
        $this->adapter->shouldReceive('locateTemplate')->once()->with('wc-template-hooks.php', true, true)->andReturn('');

        $this->wc->loadThemeTemplateHooks();
    });

    it('declares WooCommerce theme support', function (): void {
        $this->adapter->shouldReceive('addThemeSupport')->once()->with('woocommerce');

        $this->wc->addThemeSupport();
    });
});

describe('WooCommerce::reviewsTemplate()', function (): void {
    it('keeps a template that does not come from WooCommerce', function (): void {
        $this->domainService->shouldReceive('createTemplate')->with('/theme/comments.php')->andReturn(Template::fromPath('/theme/comments.php'));
        $this->domainService->shouldReceive('getAllTemplatePaths')->andReturn(['/wc/templates/']);

        expect($this->wc->reviewsTemplate('/theme/comments.php'))->toBe('/theme/comments.php');
    });

    it('resolves a WooCommerce template through the theme', function (): void {
        $this->domainService->shouldReceive('createTemplate')->andReturn(Template::fromPath('/wc/templates/single-product-reviews.php'));
        $this->domainService->shouldReceive('getAllTemplatePaths')->andReturn(['/wc/templates/']);
        $this->domainService->shouldReceive('getWooCommerceTemplatePath')->andReturn('woocommerce/');
        $this->templateFinder->shouldReceive('locate')->with('woocommerce/single-product-reviews.php')->andReturn([]);

        expect($this->wc->reviewsTemplate('/wc/templates/single-product-reviews.php'))->toBe('/wc/templates/single-product-reviews.php');
    });
});

describe('WooCommerce::template() with a theme template', function (): void {
    it('gives the file name back on the WooCommerce status screen', function (): void {
        ($this->findThemeTemplate)($this->bladeFile);
        $this->domainService->shouldReceive('isWooCommerceStatusScreen')->once()->with(false, false, null)->andReturn(true);

        expect($this->wc->template('/wc/templates/single-product.php'))->toBe($this->bladeFile);
    });

    it('returns the loader of the Blade view that matches the theme template', function (): void {
        ($this->findThemeTemplate)($this->bladeFile);
        $this->domainService->shouldReceive('isWooCommerceStatusScreen')->andReturn(false);
        $this->templateFinder->shouldReceive('getViewNameFromPath')->once()->with(realpath($this->bladeFile))->andReturn('woocommerce.single-product');
        $this->viewFactory->shouldReceive('exists')->with('woocommerce.single-product')->andReturn(true);
        $this->viewFactory->shouldReceive('make')->with('woocommerce.single-product')->andReturn(Mockery::mock(['makeLoader' => '/cache/loader.php']));

        expect($this->wc->template('/wc/templates/single-product.php'))->toBe('/cache/loader.php');
    });

    it('returns the Blade file itself when its view name cannot be found', function (?string $viewName): void {
        ($this->findThemeTemplate)($this->bladeFile);
        $this->domainService->shouldReceive('isWooCommerceStatusScreen')->andReturn(false);
        $this->templateFinder->shouldReceive('getViewNameFromPath')->andReturn($viewName);
        $this->viewFactory->shouldReceive('exists')->never();

        expect($this->wc->template('/wc/templates/single-product.php'))->toBe($this->bladeFile);
    })->with([null, '', '0']);

    it('returns the Blade file itself when the view does not exist', function (): void {
        ($this->findThemeTemplate)($this->bladeFile);
        $this->domainService->shouldReceive('isWooCommerceStatusScreen')->andReturn(false);
        $this->templateFinder->shouldReceive('getViewNameFromPath')->andReturn('woocommerce.single-product');
        $this->viewFactory->shouldReceive('exists')->with('woocommerce.single-product')->andReturn(false);

        expect($this->wc->template('/wc/templates/single-product.php'))->toBe($this->bladeFile);
    });

    it('returns the Blade file itself when it is not on disk', function (): void {
        $missing = $this->tmp.'/missing.blade.php';
        ($this->findThemeTemplate)($missing);
        $this->domainService->shouldReceive('isWooCommerceStatusScreen')->andReturn(false);
        $this->templateFinder->shouldReceive('getViewNameFromPath')->never();

        expect($this->wc->template('/wc/templates/single-product.php'))->toBe($missing);
    });
});

describe('WooCommerce::template() choice of the template to locate', function (): void {
    it('locates the template name when the template is a bare name and the name is a file', function (string $templateName): void {
        $this->domainService->shouldReceive('getWooCommerceTemplatePath')->andReturn('woocommerce/');
        $this->domainService->shouldReceive('getAllTemplatePaths')->andReturn([]);
        $this->domainService->shouldReceive('createTemplate')->once()->with($templateName)->andReturn(Template::fromPath('/'.$templateName));
        $this->templateFinder->shouldReceive('locate')->andReturn([]);

        $this->wc->template('single-product', $templateName);
    })->with(['a path' => ['content/single-product'], 'a php file' => ['single-product.php']]);

    it('locates the template name when the template is a bare name and the name is a bare name too', function (): void {
        $this->domainService->shouldReceive('getWooCommerceTemplatePath')->andReturn('woocommerce/');
        $this->domainService->shouldReceive('getAllTemplatePaths')->andReturn([]);
        $this->domainService->shouldReceive('createTemplate')->once()->with('product')->andReturn(Template::fromPath('/product'));
        $this->templateFinder->shouldReceive('locate')->andReturn([]);

        $this->wc->template('single-product', 'product');
    });

    it('locates the template itself when it has no name to go by', function (string $templateName): void {
        $this->domainService->shouldReceive('getWooCommerceTemplatePath')->andReturn('woocommerce/');
        $this->domainService->shouldReceive('getAllTemplatePaths')->andReturn([]);
        $this->domainService->shouldReceive('createTemplate')->once()->with('single-product')->andReturn(Template::fromPath('/single-product'));
        $this->templateFinder->shouldReceive('locate')->andReturn([]);

        expect($this->wc->template('single-product', $templateName))->toBe('single-product');
    })->with(['empty' => [''], 'zero' => ['0']]);
});
