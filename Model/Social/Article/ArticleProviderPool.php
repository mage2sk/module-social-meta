<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Model\Social\Article;

use Panth\SocialMeta\Api\ArticleDataProviderInterface;
use Panth\SocialMeta\Api\Data\ArticleDataInterface;

class ArticleProviderPool
{
    /**
     * @var ArticleDataProviderInterface[]
     */
    private array $providers;

    /**
     * @param ArticleDataProviderInterface[] $providers
     */
    public function __construct(array $providers = [])
    {
        $this->providers = $providers;
    }

    public function resolveArticleData(): ?ArticleDataInterface
    {
        foreach ($this->providers as $provider) {
            if (!$provider instanceof ArticleDataProviderInterface) {
                continue;
            }
            try {
                $data = $provider->getArticleData();
            } catch (\Throwable) {
                continue;
            }
            if ($data instanceof ArticleDataInterface) {
                return $data;
            }
        }

        return null;
    }
}
