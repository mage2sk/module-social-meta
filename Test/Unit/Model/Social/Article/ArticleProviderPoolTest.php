<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Test\Unit\Model\Social\Article;

use Panth\SocialMeta\Api\ArticleDataProviderInterface;
use Panth\SocialMeta\Model\Social\Article\ArticleData;
use Panth\SocialMeta\Model\Social\Article\ArticleProviderPool;
use PHPUnit\Framework\TestCase;

class ArticleProviderPoolTest extends TestCase
{
    private function provider(?ArticleData $data, ?\Throwable $error = null): ArticleDataProviderInterface
    {
        $provider = $this->createStub(ArticleDataProviderInterface::class);
        if ($error !== null) {
            $provider->method('getArticleData')->willThrowException($error);
        } else {
            $provider->method('getArticleData')->willReturn($data);
        }
        return $provider;
    }

    public function testEmptyPoolReturnsNull(): void
    {
        $this->assertNull((new ArticleProviderPool())->resolveArticleData());
    }

    public function testFirstProviderWithDataWins(): void
    {
        $first = new ArticleData('2024-01-01');
        $second = new ArticleData('2025-01-01');
        $pool = new ArticleProviderPool([
            'invalid' => new \stdClass(),
            'empty' => $this->provider(null),
            'broken' => $this->provider(null, new \RuntimeException('boom')),
            'first' => $this->provider($first),
            'second' => $this->provider($second),
        ]);

        $this->assertSame($first, $pool->resolveArticleData());
    }

    public function testAllProvidersEmptyOrFailingReturnsNull(): void
    {
        $pool = new ArticleProviderPool([
            $this->provider(null),
            $this->provider(null, new \Error('fatal')),
        ]);

        $this->assertNull($pool->resolveArticleData());
    }

    public function testLaterProviderIsNotCalledOnceResolved(): void
    {
        $later = $this->createMock(ArticleDataProviderInterface::class);
        $later->expects($this->never())->method('getArticleData');

        $data = new ArticleData();
        $pool = new ArticleProviderPool([$this->provider($data), $later]);

        $this->assertSame($data, $pool->resolveArticleData());
    }
}
