<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Test\Unit\Model\Social;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Panth\SocialMeta\Model\Social\OpenGraphResolver;
use Panth\SocialMeta\Model\Social\TwitterCardResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TwitterCardResolverTest extends TestCase
{
    private function resolver(array $ogTags, array $config, bool $configThrows = false): TwitterCardResolver
    {
        $og = $this->createStub(OpenGraphResolver::class);
        $og->method('resolve')->willReturn($ogTags);

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        if ($configThrows) {
            $scopeConfig->method('getValue')->willThrowException(new \RuntimeException('config'));
        } else {
            $scopeConfig->method('getValue')->willReturnCallback(static fn ($path) => $config[$path] ?? null);
        }

        return new TwitterCardResolver($og, $scopeConfig);
    }

    public function testNoOpenGraphTagsMeansNoTwitterTags(): void
    {
        $resolver = $this->resolver([], [TwitterCardResolver::XML_TWITTER_SITE => 'shop']);
        $this->assertSame([], $resolver->resolve());
    }

    public function testMapsOpenGraphTagsAndSiteHandle(): void
    {
        $resolver = $this->resolver(
            [
                'og:title' => 'Title',
                'og:description' => 'Desc',
                'og:image' => 'https://shop.test/a.jpg',
                'og:image:alt' => 'Alt text',
                'og:url' => 'https://shop.test/',
            ],
            [
                TwitterCardResolver::XML_TWITTER_CARD_TYPE => 'summary',
                TwitterCardResolver::XML_TWITTER_SITE => '  @@shop ',
            ]
        );

        $this->assertSame([
            'twitter:card' => 'summary',
            'twitter:title' => 'Title',
            'twitter:description' => 'Desc',
            'twitter:image' => 'https://shop.test/a.jpg',
            'twitter:image:alt' => 'Alt text',
            'twitter:site' => '@shop',
        ], $resolver->resolve());
    }

    public function testMissingValuesAreFilteredOut(): void
    {
        $resolver = $this->resolver(['og:title' => 'Only title'], [TwitterCardResolver::XML_TWITTER_SITE => ' @ ']);

        $this->assertSame(
            ['twitter:card' => 'summary', 'twitter:title' => 'Only title'],
            $resolver->resolve()
        );
    }

    public static function cardTypes(): array
    {
        return [
            'summary' => ['summary', 'summary'],
            'large' => ['summary_large_image', 'summary_large_image'],
            'unknown' => ['player', 'summary_large_image'],
            'unset' => [null, 'summary_large_image'],
        ];
    }

    #[DataProvider('cardTypes')]
    public function testCardTypeWhitelist(?string $configured, string $expected): void
    {
        $resolver = $this->resolver(
            ['og:title' => 'T', 'og:image' => 'https://shop.test/i.png'],
            [TwitterCardResolver::XML_TWITTER_CARD_TYPE => $configured]
        );
        $this->assertSame($expected, $resolver->resolve()['twitter:card']);
    }

    public function testPageWithoutImageUsesSummaryCardWhateverTheConfig(): void
    {
        $resolver = $this->resolver(
            ['og:title' => 'Home', 'og:description' => 'Welcome', 'og:image:alt' => 'Home'],
            [TwitterCardResolver::XML_TWITTER_CARD_TYPE => 'summary_large_image']
        );

        $this->assertSame([
            'twitter:card' => 'summary',
            'twitter:title' => 'Home',
            'twitter:description' => 'Welcome',
        ], $resolver->resolve());
    }

    public function testImageWithoutAltOmitsAltTag(): void
    {
        $tags = $this->resolver(['og:image' => 'https://shop.test/i.png'], [])->resolve();

        $this->assertSame('summary_large_image', $tags['twitter:card']);
        $this->assertArrayNotHasKey('twitter:image:alt', $tags);
    }

    public function testConfigFailureUsesSafeDefaults(): void
    {
        $resolver = $this->resolver(['og:title' => 'T', 'og:image' => 'https://shop.test/i.png'], [], true);

        $this->assertSame([
            'twitter:card' => 'summary_large_image',
            'twitter:title' => 'T',
            'twitter:image' => 'https://shop.test/i.png',
        ], $resolver->resolve());
    }
}
