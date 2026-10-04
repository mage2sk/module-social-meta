<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Test\Unit\Model\Social;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\SocialMeta\Model\Social\OpenGraphResolver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class OpenGraphResolverTest extends TestCase
{
    private function buildResolver(Product $product, ?string $placeholder = null): OpenGraphResolver
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getName')->willReturn('Default Store View');
        $store->method('getBaseUrl')->willReturn('https://shop.test/media/');
        $store->method('getCurrentUrl')->willReturn('https://shop.test/item.html');
        $store->method('getCurrentCurrencyCode')->willReturn('USD');
        $store->method('getConfig')->willReturnCallback(
            static fn (string $path) => $path === 'catalog/placeholder/image_placeholder' ? $placeholder : null
        );

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(
            static fn (string $key) => $key === 'current_product' ? $product : null
        );

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);

        return new OpenGraphResolver($registry, $storeManager, $scopeConfig);
    }

    private function buildProduct(array $data, bool $salable): Product
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isSalable', 'getPriceInfo', 'getAttributeText', 'getUrlModel', 'getImage'])
            ->getMock();
        $product->method('isSalable')->willReturn($salable);
        $product->method('getPriceInfo')->willThrowException(new \RuntimeException('no price'));
        $product->method('getAttributeText')->willReturn('');
        $product->method('getUrlModel')->willThrowException(new \RuntimeException('no url'));
        $product->method('getImage')->willReturn($data['image'] ?? null);
        $product->setData(array_merge(['id' => 5, 'entity_id' => 5], $data));

        return $product;
    }

    public function testOgAttributesTakePriority(): void
    {
        $product = $this->buildProduct([
            'name' => 'Shirt',
            'meta_title' => 'Shirt meta',
            'meta_description' => 'Meta description',
            'image' => '/s/h/shirt.jpg',
            'og_title' => 'Share title',
            'og_description' => 'Share description',
            'og_image' => 'wysiwyg/share.jpg',
        ], true);

        $tags = $this->buildResolver($product)->resolve();

        $this->assertSame('Share title', $tags['og:title']);
        $this->assertSame('Share description', $tags['og:description']);
        $this->assertSame('https://shop.test/media/wysiwyg/share.jpg', $tags['og:image']);
        $this->assertSame('in stock', $tags['product:availability']);
    }

    public function testEmptyOgAttributesFallBack(): void
    {
        $product = $this->buildProduct([
            'name' => 'Shirt',
            'meta_title' => 'Shirt meta',
            'meta_description' => 'Meta description',
            'image' => '/s/h/shirt.jpg',
            'og_title' => '',
            'og_image' => 'javascript:alert(1)',
        ], false);

        $tags = $this->buildResolver($product)->resolve();

        $this->assertSame('Shirt meta', $tags['og:title']);
        $this->assertSame('Meta description', $tags['og:description']);
        $this->assertSame('https://shop.test/media/catalog/product/s/h/shirt.jpg', $tags['og:image']);
        $this->assertSame('out of stock', $tags['product:availability']);
    }

    public function testAbsoluteOgImageIsKept(): void
    {
        $product = $this->buildProduct(['name' => 'Shirt', 'og_image' => 'https://cdn.test/a.jpg'], true);

        $tags = $this->buildResolver($product)->resolve();

        $this->assertSame('https://cdn.test/a.jpg', $tags['og:image']);
    }

    public function testImageOmittedWithoutPlaceholder(): void
    {
        $product = $this->buildProduct(['name' => 'Shirt'], true);

        $tags = $this->buildResolver($product)->resolve();

        $this->assertArrayNotHasKey('og:image', $tags);
    }

    public function testConfiguredPlaceholderIsUsed(): void
    {
        $product = $this->buildProduct(['name' => 'Shirt'], true);

        $tags = $this->buildResolver($product, 'default/ph.jpg')->resolve();

        $this->assertSame('https://shop.test/media/catalog/product/placeholder/default/ph.jpg', $tags['og:image']);
    }
}
