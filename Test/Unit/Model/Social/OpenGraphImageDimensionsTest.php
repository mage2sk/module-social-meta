<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Test\Unit\Model\Social;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\SocialMeta\Model\Social\OpenGraphResolver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class OpenGraphImageDimensionsTest extends TestCase
{
    private const GIF_1X1 = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    private string $tmpFile = '';

    protected function setUp(): void
    {
        $this->tmpFile = (string) tempnam(sys_get_temp_dir(), 'ogimg');
        $gif = base64_decode(self::GIF_1X1);
        file_put_contents($this->tmpFile, substr_replace($gif, pack('vv', 1200, 630), 6, 4));
    }

    protected function tearDown(): void
    {
        if ($this->tmpFile !== '' && is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    private function resolver(string $image, ?Filesystem $filesystem, array $config = []): OpenGraphResolver
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getName')->willReturn('Main Store');
        $store->method('getBaseUrl')->willReturnCallback(
            static fn ($type = UrlInterface::URL_TYPE_LINK) => $type === UrlInterface::URL_TYPE_MEDIA
                ? 'https://shop.test/media/'
                : 'https://shop.test/'
        );
        $store->method('getCurrentUrl')->willReturn('https://shop.test/p.html');
        $store->method('getCurrentCurrencyCode')->willReturn('USD');
        $store->method('getConfig')->willReturn(null);

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isSalable', 'getPriceInfo', 'getAttributeText', 'getUrlModel', 'getImage'])
            ->getMock();
        $product->method('isSalable')->willReturn(true);
        $product->method('getPriceInfo')->willThrowException(new \RuntimeException('no price'));
        $product->method('getAttributeText')->willReturn(false);
        $product->method('getUrlModel')->willThrowException(new \RuntimeException('no url'));
        $product->method('getImage')->willReturn($image);
        $product->setData(['id' => 5, 'entity_id' => 5, 'name' => 'Tee & "Cap"']);

        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(
            static fn ($key) => $key === 'current_product' ? $product : null
        );

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(static fn ($path) => $config[$path] ?? null);

        return new OpenGraphResolver($registry, $storeManager, $scopeConfig, null, null, $filesystem);
    }

    private function filesystem(bool $isFile, ?string $expectedRelative = null): Filesystem
    {
        $directory = $this->createMock(ReadInterface::class);
        if ($expectedRelative !== null) {
            $directory->expects($this->once())->method('isFile')->with($expectedRelative)->willReturn($isFile);
        } else {
            $directory->method('isFile')->willReturn($isFile);
        }
        $directory->method('getAbsolutePath')->willReturn($this->tmpFile);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryRead')->with(DirectoryList::MEDIA)->willReturn($directory);
        return $filesystem;
    }

    public function testLocalMediaImageGetsDimensionsTypeAndAlt(): void
    {
        $tags = $this->resolver(
            '/t/e/tee%20cap.gif',
            $this->filesystem(true, 'catalog/product/t/e/tee cap.gif')
        )->resolve();

        $this->assertSame('1200', $tags['og:image:width']);
        $this->assertSame('630', $tags['og:image:height']);
        $this->assertSame('image/gif', $tags['og:image:type']);
        $this->assertSame('Tee & "Cap"', $tags['og:image:alt']);

        $keys = array_keys($tags);
        $imageIndex = (int) array_search('og:image', $keys, true);
        $this->assertSame(
            ['og:image', 'og:image:width', 'og:image:height', 'og:image:type', 'og:image:alt', 'og:url'],
            array_slice($keys, $imageIndex, 6)
        );
    }

    public function testMissingFileGivesAltOnly(): void
    {
        $tags = $this->resolver('/a/b/gone.jpg', $this->filesystem(false))->resolve();

        $this->assertArrayNotHasKey('og:image:width', $tags);
        $this->assertArrayNotHasKey('og:image:height', $tags);
        $this->assertSame('Tee & "Cap"', $tags['og:image:alt']);
    }

    public function testWithoutFilesystemNoDimensions(): void
    {
        $tags = $this->resolver('/a/b/c.jpg', null)->resolve();

        $this->assertArrayNotHasKey('og:image:width', $tags);
        $this->assertArrayHasKey('og:image:alt', $tags);
    }

    public function testExternalImageIsNotMeasured(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->never())->method('getDirectoryRead');
        $tags = $this->resolver(
            'no_selection',
            $filesystem,
            [OpenGraphResolver::XML_DEFAULT_OG_IMAGE => 'https://cdn.test/share.jpg']
        )->resolve();

        $this->assertSame('https://cdn.test/share.jpg', $tags['og:image']);
        $this->assertArrayNotHasKey('og:image:width', $tags);
    }

    public function testNonImageFileGivesNoDimensions(): void
    {
        file_put_contents($this->tmpFile, 'not an image');
        $tags = $this->resolver('/a/b/c.jpg', $this->filesystem(true))->resolve();

        $this->assertArrayNotHasKey('og:image:width', $tags);
        $this->assertArrayNotHasKey('og:image:type', $tags);
    }

    public function testFilesystemFailureIsIgnored(): void
    {
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryRead')->willThrowException(new \RuntimeException('fs'));
        $tags = $this->resolver('/a/b/c.jpg', $filesystem)->resolve();

        $this->assertSame('https://shop.test/media/catalog/product/a/b/c.jpg', $tags['og:image']);
        $this->assertArrayNotHasKey('og:image:width', $tags);
    }

    public static function unsafePaths(): array
    {
        return [
            'traversal' => ['/../../etc/passwd'],
            'encoded traversal' => ['/%2e%2e/secret.png'],
            'null byte' => ['/a%00.png'],
        ];
    }

    #[DataProvider('unsafePaths')]
    public function testUnsafeRelativePathsAreNotRead(string $image): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->never())->method('getDirectoryRead');
        $tags = $this->resolver($image, $filesystem)->resolve();

        $this->assertArrayNotHasKey('og:image:width', $tags);
    }

    public function testNoImageMeansNoAlt(): void
    {
        $tags = $this->resolver('no_selection', $this->createStub(Filesystem::class))->resolve();

        $this->assertArrayNotHasKey('og:image', $tags);
        $this->assertArrayNotHasKey('og:image:alt', $tags);
    }
}
