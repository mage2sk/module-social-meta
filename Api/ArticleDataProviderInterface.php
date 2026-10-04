<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Api;

use Panth\SocialMeta\Api\Data\ArticleDataInterface;

interface ArticleDataProviderInterface
{
    public function getArticleData(): ?ArticleDataInterface;
}
