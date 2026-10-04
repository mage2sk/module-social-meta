<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Test\Unit\Model\Social;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Url as ProductUrl;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceInfo\Base as PriceInfo;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\SocialMeta\Model\Social\Article\ArticleData;
use Panth\SocialMeta\Model\Social\Article\ArticleProviderPool;
use Panth\SocialMeta\Model\Social\OpenGraphResolver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class OpenGraphResolverCoverageTest extends TestCase
{
    /**
     * @var array
     */
    private array $registryData = [];

    /**
     * @var array
     */
    private array $storeConfig = [];

    /**
     * @var array
     */
    private array $scopeValues = [];

    /**
     * @var array
     */
    private array $flags = [];

    private string $currentUrl = 'https://shop.test/page';

    private string $currency = 'USD';

    private bool $scopeThrows = false;

    private function store(): Store
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getName')->willReturn('Main Store');
        $store->method('getBaseUrl')->willReturnCallback(
            static fn ($type = UrlInterface::URL_TYPE_LINK) => $type === UrlInterface::URL_TYPE_MEDIA
                ? 'https://shop.test/media/'
                : 'https://shop.test:8443/en/'
        );
        $store->method('getCurrentUrl')->willReturnCallback(fn () => $this->currentUrl);
        $store->method('getCurrentCurrencyCode')->willReturnCallback(fn () => $this->currency);
        $store->method('getConfig')->willReturnCallback(fn ($path) => $this->storeConfig[$path] ?? null);
        return $store;
    }

    private function resolver(?PageConfig $pageConfig = null, ?ArticleProviderPool $pool = null): OpenGraphResolver
    {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($this->store());

        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(fn ($key) => $this->registryData[$key] ?? null);

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        if ($this->scopeThrows) {
            $scopeConfig->method('getValue')->willThrowException(new \RuntimeException('config'));
            $scopeConfig->method('isSetFlag')->willThrowException(new \RuntimeException('config'));
        } else {
            $scopeConfig->method('getValue')->willReturnCallback(fn ($path) => $this->scopeValues[$path] ?? null);
            $scopeConfig->method('isSetFlag')->willReturnCallback(fn ($path) => $this->flags[$path] ?? false);
        }

        return new OpenGraphResolver($registry, $storeManager, $scopeConfig, $pageConfig, $pool);
    }

    private function pageConfig(?string $title, ?string $description): PageConfig
    {
        $pageConfig = $this->createStub(PageConfig::class);
        if ($title === null) {
            $pageConfig->method('getTitle')->willThrowException(new \RuntimeException('no title'));
        } else {
            $titleObj = $this->createStub(Title::class);
            $titleObj->method('get')->willReturn($title);
            $pageConfig->method('getTitle')->willReturn($titleObj);
        }
        if ($description === null) {
            $pageConfig->method('getDescription')->willThrowException(new \RuntimeException('no desc'));
        } else {
            $pageConfig->method('getDescription')->willReturn($description);
        }
        return $pageConfig;
    }

    private function category(array $data, string $url = '', string $imageUrl = '', $collection = null): Category
    {
        $category = $this->getMockBuilder(Category::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUrl', 'getImageUrl', 'getProductCollection'])
            ->getMock();
        $category->method('getUrl')->willReturn($url);
        $category->method('getImageUrl')->willReturn($imageUrl);
        if ($collection instanceof \Throwable) {
            $category->method('getProductCollection')->willThrowException($collection);
        } else {
            $category->method('getProductCollection')->willReturn($collection);
        }
        $category->setData(array_merge(['entity_id' => 7, 'id' => 7], $data));
        return $category;
    }

    private function productCollection(array $images): ProductCollection
    {
        $collection = $this->createStub(ProductCollection::class);
        foreach (['addAttributeToSelect', 'addAttributeToFilter', 'addFieldToFilter', 'setPageSize', 'setCurPage']
            as $method) {
            $collection->method($method)->willReturnSelf();
        }
        $collection->method('getIterator')->willReturn(new \ArrayIterator(
            array_map(static fn ($image) => new DataObject(['image' => $image]), $images)
        ));
        return $collection;
    }

    private function product(array $data, array $behaviour = []): Product
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isSalable', 'getPriceInfo', 'getAttributeText', 'getUrlModel', 'getImage'])
            ->getMock();

        if (isset($behaviour['salable_error'])) {
            $product->method('isSalable')->willThrowException(new \RuntimeException('stock'));
        } else {
            $product->method('isSalable')->willReturn($behaviour['salable'] ?? true);
        }

        if (isset($behaviour['price'])) {
            $price = $this->createStub(PriceInterface::class);
            $price->method('getValue')->willReturn($behaviour['price']);
            $priceInfo = $this->createStub(PriceInfo::class);
            $priceInfo->method('getPrice')->willReturn($price);
            $product->method('getPriceInfo')->willReturn($priceInfo);
        } else {
            $product->method('getPriceInfo')->willThrowException(new \RuntimeException('no price'));
        }

        if (isset($behaviour['brand_error'])) {
            $product->method('getAttributeText')->willThrowException(new \RuntimeException('attr'));
        } else {
            $product->method('getAttributeText')->willReturn($behaviour['brand'] ?? false);
        }

        if (isset($behaviour['url'])) {
            $urlModel = $this->createStub(ProductUrl::class);
            $urlModel->method('getUrl')->willReturn($behaviour['url']);
            $product->method('getUrlModel')->willReturn($urlModel);
        } else {
            $product->method('getUrlModel')->willThrowException(new \RuntimeException('no url'));
        }

        $product->method('getImage')->willReturn($data['image'] ?? null);
        $product->setData(array_merge(['id' => 5, 'entity_id' => 5], $data));
        return $product;
    }

    public function testStoreFailureReturnsNoTags(): void
    {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willThrowException(new \RuntimeException('no store'));

        $resolver = new OpenGraphResolver(
            $this->createStub(Registry::class),
            $storeManager,
            $this->createStub(ScopeConfigInterface::class)
        );

        $this->assertSame([], $resolver->resolve());
    }

    public function testGenericPageUsesPageConfigAndStoreSettings(): void
    {
        $this->currentUrl = 'https://shop.test/about-us/?utm=1#top';
        $this->scopeValues = [
            'general/store_information/name' => 'Brand Co',
            'general/locale/code' => 'en_GB',
            OpenGraphResolver::XML_DEFAULT_OG_IMAGE => 'default/share.png',
        ];

        $tags = $this->resolver($this->pageConfig('  About Us  ', ' About <b>our</b> shop '))->resolve();

        $this->assertSame([
            'og:type' => 'website',
            'og:title' => 'About Us',
            'og:description' => 'About our shop',
            'og:image' => 'https://shop.test/media/panth_seo/og/default/share.png',
            'og:image:alt' => 'About Us',
            'og:url' => 'https://shop.test/about-us',
            'og:site_name' => 'Brand Co',
            'og:locale' => 'en_GB',
        ], $tags);
    }

    public function testGenericPageFallsBackToStoreDefaults(): void
    {
        $this->currentUrl = 'https://shop.test/';
        $this->storeConfig = [
            'design/head/default_description' => 'Default store description',
            'design/header/logo_src' => '/stores/1/logo.svg',
        ];

        $tags = $this->resolver($this->pageConfig(null, null))->resolve();

        $this->assertSame('Main Store', $tags['og:title']);
        $this->assertSame('Default store description', $tags['og:description']);
        $this->assertSame('https://shop.test/media/logo/stores/1/logo.svg', $tags['og:image']);
        $this->assertSame('https://shop.test/', $tags['og:url']);
        $this->assertSame('Main Store', $tags['og:site_name']);
        $this->assertArrayNotHasKey('og:locale', $tags);
    }

    public function testBlankPageConfigWithoutStoreDefaultsOmitsDescriptionAndImage(): void
    {
        $tags = $this->resolver($this->pageConfig('   ', ''))->resolve();

        $this->assertSame('Main Store', $tags['og:title']);
        $this->assertArrayNotHasKey('og:description', $tags);
        $this->assertArrayNotHasKey('og:image', $tags);
    }

    public static function defaultOgImages(): array
    {
        return [
            'relative' => ['banners/og.jpg', 'https://shop.test/media/panth_seo/og/banners/og.jpg'],
            'absolute https' => ['https://cdn.test/og.jpg', 'https://cdn.test/og.jpg'],
            'absolute http upper' => ['HTTP://cdn.test/og.jpg', 'HTTP://cdn.test/og.jpg'],
            'traversal' => ['../../etc/x.jpg', null],
            'leading slash' => ['/abs/og.jpg', null],
            'backslash' => ['a\\b.jpg', null],
            'nul byte' => ["a\0b.jpg", null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('defaultOgImages')]
    public function testDefaultOgImageValidation(string $configured, ?string $expected): void
    {
        $this->scopeValues = [OpenGraphResolver::XML_DEFAULT_OG_IMAGE => $configured];

        $tags = $this->resolver()->resolve();

        $this->assertSame($expected, $tags['og:image'] ?? null);
    }

    public function testCategoryUsesOgOverrides(): void
    {
        $this->registryData['current_category'] = $this->category(
            [
                'name' => 'Shirts',
                'meta_title' => 'Shirts meta',
                'og_title' => ' Share shirts ',
                'og_description' => '<p>Fish &amp; Chips</p><script>var a = 1;</script>',
                'og_image' => '/media/wysiwyg/cat.jpg',
            ],
            'https://shop.test/men/shirts/?p=2'
        );

        $tags = $this->resolver()->resolve();

        $this->assertSame('website', $tags['og:type']);
        $this->assertSame('Share shirts', $tags['og:title']);
        $this->assertSame('Fish & Chips', $tags['og:description']);
        $this->assertSame('https://shop.test/media/wysiwyg/cat.jpg', $tags['og:image']);
        $this->assertSame('https://shop.test/men/shirts', $tags['og:url']);
        $this->assertArrayNotHasKey('product:availability', $tags);
    }

    public function testCategoryFallsBackToMetaAndCategoryImage(): void
    {
        $this->registryData['current_category'] = $this->category(
            ['name' => 'Shirts', 'meta_title' => 'Shirts meta', 'meta_description' => 'Shirt meta desc'],
            '',
            '/media/catalog/category/shirts.jpg'
        );

        $tags = $this->resolver()->resolve();

        $this->assertSame('Shirts meta', $tags['og:title']);
        $this->assertSame('Shirt meta desc', $tags['og:description']);
        $this->assertSame('https://shop.test:8443/media/catalog/category/shirts.jpg', $tags['og:image']);
        $this->assertSame('https://shop.test/page', $tags['og:url']);
    }

    public function testCategoryFallsBackToNameDescriptionAndFirstProductImage(): void
    {
        $this->registryData['current_category'] = $this->category(
            ['name' => 'Shirts', 'description' => 'Category body'],
            '',
            '//cdn.test/protocol-relative.jpg',
            $this->productCollection(['no_selection', '/a/b/first.jpg', '/c/d/second.jpg'])
        );

        $tags = $this->resolver()->resolve();

        $this->assertSame('Shirts', $tags['og:title']);
        $this->assertSame('Category body', $tags['og:description']);
        $this->assertSame('https://shop.test/media/catalog/product/a/b/first.jpg', $tags['og:image']);
    }

    public function testCategoryImageLookupFailureFallsBackToPlaceholder(): void
    {
        $this->storeConfig = ['catalog/placeholder/image_placeholder' => 'stores/1/ph.png'];
        $this->registryData['current_category'] = $this->category(
            ['name' => 'Shirts', 'og_image' => 'data:image/png;base64,AAAA'],
            '',
            '',
            new \RuntimeException('collection failure')
        );

        $tags = $this->resolver()->resolve();

        $this->assertSame('https://shop.test/media/catalog/product/placeholder/stores/1/ph.png', $tags['og:image']);
    }

    public function testCategoryWithNullCollectionAndAbsoluteImageUrl(): void
    {
        $this->registryData['current_category'] = $this->category(
            ['name' => 'Shirts'],
            '',
            'https://cdn.test/cat.jpg'
        );
        $this->assertSame('https://cdn.test/cat.jpg', $this->resolver()->resolve()['og:image']);

        $this->registryData['current_category'] = $this->category(['name' => 'Shirts']);
        $this->assertArrayNotHasKey('og:image', $this->resolver()->resolve());
    }

    public function testProductWithoutIdIsTreatedAsCategoryPage(): void
    {
        $product = $this->product(['name' => 'Ghost']);
        $product->setData('id', null);
        $product->setData('entity_id', null);
        $this->registryData['current_product'] = $product;
        $this->registryData['current_category'] = $this->category(['name' => 'Parent']);

        $tags = $this->resolver()->resolve();

        $this->assertSame('website', $tags['og:type']);
        $this->assertSame('Parent', $tags['og:title']);
        $this->assertArrayNotHasKey('product:availability', $tags);
    }

    public function testProductCommerceTags(): void
    {
        $this->currency = 'EUR';
        $this->registryData['current_product'] = $this->product(
            ['name' => 'Shirt', 'short_description' => '<p>Short   copy</p>'],
            ['price' => 19.9, 'brand' => 'Acme', 'url' => 'https://shop.test/shirt.html?color=red']
        );

        $tags = $this->resolver()->resolve();

        $this->assertSame('product', $tags['og:type']);
        $this->assertSame('Shirt', $tags['og:title']);
        $this->assertSame('Short copy', $tags['og:description']);
        $this->assertSame('https://shop.test/shirt.html', $tags['og:url']);
        $this->assertSame('19.90', $tags['product:price:amount']);
        $this->assertSame('EUR', $tags['product:price:currency']);
        $this->assertSame('in stock', $tags['product:availability']);
        $this->assertSame('Acme', $tags['product:brand']);
    }

    public function testProductWithoutCurrencyOrBrandAndStockErrors(): void
    {
        $this->currency = '';
        $this->registryData['current_product'] = $this->product(
            ['name' => 'Shirt', 'description' => 'Long description', 'image' => 'no_selection'],
            ['price' => 5.0, 'brand_error' => true, 'salable_error' => true, 'url' => '']
        );

        $tags = $this->resolver()->resolve();

        $this->assertArrayNotHasKey('product:price:amount', $tags);
        $this->assertArrayNotHasKey('product:price:currency', $tags);
        $this->assertArrayNotHasKey('product:brand', $tags);
        $this->assertSame('in stock', $tags['product:availability']);
        $this->assertSame('Long description', $tags['og:description']);
        $this->assertSame('https://shop.test/page', $tags['og:url']);
        $this->assertArrayNotHasKey('og:image', $tags);
    }

    public function testZeroPriceEmitsNoPriceTags(): void
    {
        $this->registryData['current_product'] = $this->product(['name' => 'Free'], ['price' => 0]);

        $tags = $this->resolver()->resolve();

        $this->assertArrayNotHasKey('product:price:amount', $tags);
    }

    public function testLongDescriptionIsTruncatedOnWordBoundaryLength(): void
    {
        $this->registryData['current_product'] = $this->product([
            'name' => 'Shirt',
            'meta_description' => str_repeat('word ', 60),
        ]);

        $description = $this->resolver()->resolve()['og:description'];

        $this->assertSame(str_repeat('word ', 39) . 'wo...', $description);
        $this->assertSame(200, mb_strlen($description));
    }

    public static function productOgImages(): array
    {
        return [
            'protocol relative' => ['//evil.test/x.jpg'],
            'traversal' => ['wysiwyg/../x.jpg'],
            'backslash' => ['wysiwyg\\x.jpg'],
            'scheme' => ['ftp://host/x.jpg'],
        ];
    }

    #[DataProvider('productOgImages')]
    public function testUnsafeProductOgImageFallsBackToProductImage(string $ogImage): void
    {
        $this->registryData['current_product'] = $this->product([
            'name' => 'Shirt',
            'og_image' => $ogImage,
            'image' => '/s/h/shirt.jpg',
        ]);

        $tags = $this->resolver()->resolve();

        $this->assertSame('https://shop.test/media/catalog/product/s/h/shirt.jpg', $tags['og:image']);
    }

    public function testNonScalarOgValueIsIgnored(): void
    {
        $this->registryData['current_product'] = $this->product([
            'name' => 'Shirt',
            'meta_title' => 'Meta',
            'og_title' => ['array'],
        ]);

        $this->assertSame('Meta', $this->resolver()->resolve()['og:title']);
    }

    public function testConfigFailuresDropOptionalTags(): void
    {
        $this->scopeThrows = true;

        $tags = $this->resolver(null, $this->createStub(ArticleProviderPool::class))->resolve();

        $this->assertSame('Main Store', $tags['og:title']);
        $this->assertSame('website', $tags['og:type']);
        $this->assertArrayNotHasKey('og:site_name', $tags);
        $this->assertArrayNotHasKey('og:locale', $tags);
        $this->assertArrayNotHasKey('article:published_time', $tags);
    }

    public function testArticleTagsAreAppliedWhenEnabled(): void
    {
        $this->flags[OpenGraphResolver::XML_ARTICLE_ENABLED] = true;
        $pool = $this->createStub(ArticleProviderPool::class);
        $pool->method('resolveArticleData')->willReturn(
            new ArticleData('2024-01-02 03:04:05', '2024-02-03 04:05:06', ' Jane ', ' News ', [], ' ')
        );

        $tags = $this->resolver(null, $pool)->resolve();

        $modified = date('c', strtotime('2024-02-03 04:05:06'));
        $this->assertSame('article', $tags['og:type']);
        $this->assertSame(date('c', strtotime('2024-01-02 03:04:05')), $tags['article:published_time']);
        $this->assertSame($modified, $tags['article:modified_time']);
        $this->assertSame($modified, $tags['og:updated_time']);
        $this->assertSame('Jane', $tags['article:author']);
        $this->assertSame('News', $tags['article:section']);
    }

    public function testArticleWithInvalidDatesAndCustomType(): void
    {
        $this->flags[OpenGraphResolver::XML_ARTICLE_ENABLED] = true;
        $pool = $this->createStub(ArticleProviderPool::class);
        $pool->method('resolveArticleData')->willReturn(
            new ArticleData('not a date', null, null, '', [], 'video.other')
        );

        $tags = $this->resolver(null, $pool)->resolve();

        $this->assertSame('video.other', $tags['og:type']);
        $this->assertArrayNotHasKey('article:published_time', $tags);
        $this->assertArrayNotHasKey('article:modified_time', $tags);
        $this->assertArrayNotHasKey('og:updated_time', $tags);
        $this->assertArrayNotHasKey('article:author', $tags);
        $this->assertArrayNotHasKey('article:section', $tags);
    }

    public function testArticleSkippedWhenDisabledMissingOrFailing(): void
    {
        $pool = $this->createMock(ArticleProviderPool::class);
        $pool->expects($this->never())->method('resolveArticleData');
        $this->assertSame('website', $this->resolver(null, $pool)->resolve()['og:type']);

        $this->flags[OpenGraphResolver::XML_ARTICLE_ENABLED] = true;
        $this->assertSame('website', $this->resolver()->resolve()['og:type']);

        $empty = $this->createStub(ArticleProviderPool::class);
        $empty->method('resolveArticleData')->willReturn(null);
        $this->assertSame('website', $this->resolver(null, $empty)->resolve()['og:type']);

        $failing = $this->createStub(ArticleProviderPool::class);
        $failing->method('resolveArticleData')->willThrowException(new \RuntimeException('pool'));
        $this->assertSame('website', $this->resolver(null, $failing)->resolve()['og:type']);
    }
}
