<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Model\Social;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class TwitterCardResolver
{
    public const XML_TWITTER_CARD_TYPE = 'panth_social_meta/social/twitter_card_type';
    public const XML_TWITTER_SITE      = 'panth_social_meta/social/twitter_site_handle';

    public function __construct(
        private readonly OpenGraphResolver $openGraphResolver,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function resolve(): array
    {
        $ogTags = $this->openGraphResolver->resolve();
        if ($ogTags === []) {
            return [];
        }

        $tags = [];
        $tags['twitter:card'] = $this->getCardType();
        $tags['twitter:title'] = $ogTags['og:title'] ?? '';
        $tags['twitter:description'] = $ogTags['og:description'] ?? '';
        $tags['twitter:image'] = $ogTags['og:image'] ?? '';

        $twitterSite = $this->getTwitterSite();
        if ($twitterSite !== '') {
            $tags['twitter:site'] = $twitterSite;
        }

        return array_filter($tags, static fn (string $v): bool => $v !== '');
    }

    private function getCardType(): string
    {
        try {
            $value = $this->scopeConfig->getValue(self::XML_TWITTER_CARD_TYPE, ScopeInterface::SCOPE_STORE);
            return $value !== null && $value !== '' ? (string) $value : 'summary_large_image';
        } catch (\Throwable) {
            return 'summary_large_image';
        }
    }

    private function getTwitterSite(): string
    {
        try {
            $value = $this->scopeConfig->getValue(self::XML_TWITTER_SITE, ScopeInterface::SCOPE_STORE);
            return $value !== null ? (string) $value : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
