<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Model\Social\Article;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;
use Panth\SocialMeta\Api\ArticleDataProviderInterface;
use Panth\SocialMeta\Api\Data\ArticleDataInterface;

class RegistryArticleProvider implements ArticleDataProviderInterface
{
    public const XML_ENABLED = 'panth_social_meta/social/article_registry_fallback';

    /**
     * @param string[] $registryKeys
     */
    public function __construct(
        private readonly Registry $registry,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ArticleDataFactory $articleDataFactory,
        private readonly array $registryKeys = []
    ) {
    }

    public function getArticleData(): ?ArticleDataInterface
    {
        try {
            if (!$this->scopeConfig->isSetFlag(self::XML_ENABLED, ScopeInterface::SCOPE_STORE)) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        foreach ($this->registryKeys as $key) {
            $entity = $this->registry->registry((string) $key);
            if (!is_object($entity)) {
                continue;
            }

            $published = method_exists($entity, 'getPublishedAt') ? (string) ($entity->getPublishedAt() ?? '') : '';
            $modified = method_exists($entity, 'getUpdatedAt') ? (string) ($entity->getUpdatedAt() ?? '') : '';

            if ($published === '' && $modified === '') {
                continue;
            }

            $author = method_exists($entity, 'getAuthorName') ? (string) ($entity->getAuthorName() ?? '') : '';

            return $this->articleDataFactory->create([
                'publishedTime' => $published !== '' ? $published : null,
                'modifiedTime' => $modified !== '' ? $modified : null,
                'author' => $author !== '' ? $author : null,
                'ogType' => ArticleDataInterface::OG_TYPE_ARTICLE,
            ]);
        }

        return null;
    }
}
