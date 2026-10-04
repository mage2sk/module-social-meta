<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Test\Unit\Model\Social\Article;

use Panth\SocialMeta\Api\Data\ArticleDataInterface;
use Panth\SocialMeta\Model\Social\Article\ArticleData;
use PHPUnit\Framework\TestCase;

class ArticleDataTest extends TestCase
{
    public function testDefaultsToArticleTypeAndEmptyValues(): void
    {
        $data = new ArticleData();

        $this->assertSame(ArticleDataInterface::OG_TYPE_ARTICLE, $data->getOgType());
        $this->assertNull($data->getPublishedTime());
        $this->assertNull($data->getModifiedTime());
        $this->assertNull($data->getAuthor());
        $this->assertNull($data->getSection());
        $this->assertSame([], $data->getTags());
    }

    public function testEmptyOgTypeFallsBackToArticle(): void
    {
        $this->assertSame('article', (new ArticleData(ogType: ''))->getOgType());
    }

    public function testCustomValuesArePreserved(): void
    {
        $data = new ArticleData('2024-01-01', '2024-02-01', 'Jane', 'News', ['a', 'b'], 'video.other');

        $this->assertSame('video.other', $data->getOgType());
        $this->assertSame('2024-01-01', $data->getPublishedTime());
        $this->assertSame('2024-02-01', $data->getModifiedTime());
        $this->assertSame('Jane', $data->getAuthor());
        $this->assertSame('News', $data->getSection());
        $this->assertSame(['a', 'b'], $data->getTags());
    }
}
