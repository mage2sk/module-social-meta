<?php
declare(strict_types=1);

namespace Panth\SocialMeta\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Panth\SocialMeta\Model\Social\TwitterCardResolver;

/**
 * Hyva-safe ViewModel exposing Twitter Card tags to templates.
 *
 * Previously gated by Panth_AdvancedSEO's master switch; after the split
 * this module owns its own enable flag on `panth_social_meta/social/twitter_enabled`.
 */
class TwitterCard implements ArgumentInterface
{
    public const XML_TWITTER_ENABLED = 'panth_social_meta/social/twitter_enabled';

    /**
     * @param TwitterCardResolver $twitterCardResolver
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly TwitterCardResolver $twitterCardResolver,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Whether Twitter Card output is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        try {
            return $this->scopeConfig->isSetFlag(self::XML_TWITTER_ENABLED, ScopeInterface::SCOPE_STORE);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get resolved Twitter Card tags if enabled.
     *
     * @return array<string, string>
     */
    public function getTags(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        try {
            return $this->twitterCardResolver->resolve();
        } catch (\Throwable) {
            return [];
        }
    }
}
