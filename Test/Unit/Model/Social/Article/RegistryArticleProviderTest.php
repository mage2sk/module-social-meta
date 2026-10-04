<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Test\Unit\Model\Social\Article;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;
use Panth\SocialMeta\Model\Social\Article\ArticleData;
use Panth\SocialMeta\Model\Social\Article\ArticleDataFactory;
use Panth\SocialMeta\Model\Social\Article\RegistryArticleProvider;
use PHPUnit\Framework\TestCase;

class RegistryArticleProviderTest extends TestCase
{
    /**
     * @var array
     */
    private array $createdWith = [];

    private function scopeConfig(bool $enabled): ScopeConfigInterface
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn ($path, $scope = null): bool => $enabled
                && $path === RegistryArticleProvider::XML_ENABLED
                && $scope === ScopeInterface::SCOPE_STORE
        );
        return $scopeConfig;
    }

    private function registry(array $entries): Registry
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(static fn ($key) => $entries[$key] ?? null);
        return $registry;
    }

    private function factory(): ArticleDataFactory
    {
        $factory = $this->createStub(ArticleDataFactory::class);
        $factory->method('create')->willReturnCallback(function (array $data = []): ArticleData {
            $this->createdWith[] = $data;
            return new ArticleData(...$data);
        });
        return $factory;
    }

    private function entity(?string $published, ?string $updated, ?string $author): object
    {
        return new class ($published, $updated, $author) {
            public function __construct(
                private readonly ?string $published,
                private readonly ?string $updated,
                private readonly ?string $author
            ) {
            }

            public function getPublishedAt(): ?string
            {
                return $this->published;
            }

            public function getUpdatedAt(): ?string
            {
                return $this->updated;
            }

            public function getAuthorName(): ?string
            {
                return $this->author;
            }
        };
    }

    public function testDisabledFallbackReturnsNull(): void
    {
        $registry = $this->createMock(Registry::class);
        $registry->expects($this->never())->method('registry');

        $provider = new RegistryArticleProvider($registry, $this->scopeConfig(false), $this->factory(), ['post']);

        $this->assertNull($provider->getArticleData());
    }

    public function testConfigFailureReturnsNull(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willThrowException(new \RuntimeException('no config'));

        $provider = new RegistryArticleProvider(
            $this->registry(['post' => $this->entity('2024-01-01', null, null)]),
            $scopeConfig,
            $this->factory(),
            ['post']
        );

        $this->assertNull($provider->getArticleData());
    }

    public function testSkipsMissingNonObjectAndUndatedEntries(): void
    {
        $provider = new RegistryArticleProvider(
            $this->registry([
                'scalar' => 'not an object',
                'plain' => new \stdClass(),
                'undated' => $this->entity('', null, 'Ghost'),
                'post' => $this->entity('2024-03-01 10:00:00', '', ''),
            ]),
            $this->scopeConfig(true),
            $this->factory(),
            ['missing', 'scalar', 'plain', 'undated', 'post']
        );

        $data = $provider->getArticleData();

        $this->assertNotNull($data);
        $this->assertSame('2024-03-01 10:00:00', $data->getPublishedTime());
        $this->assertNull($data->getModifiedTime());
        $this->assertNull($data->getAuthor());
        $this->assertSame([[
            'publishedTime' => '2024-03-01 10:00:00',
            'modifiedTime' => null,
            'author' => null,
            'ogType' => 'article',
        ]], $this->createdWith);
    }

    public function testModifiedOnlyEntityWithAuthor(): void
    {
        $provider = new RegistryArticleProvider(
            $this->registry(['current_blog_post' => $this->entity(null, '2024-05-05', 'Jane Doe')]),
            $this->scopeConfig(true),
            $this->factory(),
            ['current_blog_post']
        );

        $data = $provider->getArticleData();

        $this->assertNull($data->getPublishedTime());
        $this->assertSame('2024-05-05', $data->getModifiedTime());
        $this->assertSame('Jane Doe', $data->getAuthor());
        $this->assertSame('article', $data->getOgType());
    }

    public function testNoKeysOrNoMatchReturnsNull(): void
    {
        $this->assertNull(
            (new RegistryArticleProvider($this->registry([]), $this->scopeConfig(true), $this->factory()))
                ->getArticleData()
        );
        $this->assertNull(
            (new RegistryArticleProvider(
                $this->registry(['post' => new \stdClass()]),
                $this->scopeConfig(true),
                $this->factory(),
                ['post']
            ))->getArticleData()
        );
        $this->assertSame([], $this->createdWith);
    }
}
