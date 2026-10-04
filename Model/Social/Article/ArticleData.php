<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Model\Social\Article;

use Panth\SocialMeta\Api\Data\ArticleDataInterface;

class ArticleData implements ArticleDataInterface
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        private readonly ?string $publishedTime = null,
        private readonly ?string $modifiedTime = null,
        private readonly ?string $author = null,
        private readonly ?string $section = null,
        private readonly array $tags = [],
        private readonly string $ogType = ArticleDataInterface::OG_TYPE_ARTICLE
    ) {
    }

    public function getPublishedTime(): ?string
    {
        return $this->publishedTime;
    }

    public function getModifiedTime(): ?string
    {
        return $this->modifiedTime;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function getSection(): ?string
    {
        return $this->section;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function getOgType(): string
    {
        return $this->ogType !== '' ? $this->ogType : ArticleDataInterface::OG_TYPE_ARTICLE;
    }
}
