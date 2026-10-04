<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Api\Data;

interface ArticleDataInterface
{
    public const OG_TYPE_ARTICLE = 'article';

    public function getPublishedTime(): ?string;

    public function getModifiedTime(): ?string;

    public function getAuthor(): ?string;

    public function getSection(): ?string;

    /**
     * @return string[]
     */
    public function getTags(): array;

    public function getOgType(): string;
}
