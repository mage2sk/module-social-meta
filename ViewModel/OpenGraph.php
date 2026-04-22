<?php
declare(strict_types=1);

namespace Panth\SocialMeta\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Panth\SocialMeta\Model\Social\OpenGraphResolver;

/**
 * Hyva-safe ViewModel exposing Open Graph tags to templates.
 *
 * Previously gated by Panth_AdvancedSEO's master switch; after the split
 * this module owns its own enable flag on `panth_social_meta/social/og_enabled`.
 */
class OpenGraph implements ArgumentInterface
{
    public const XML_OG_ENABLED = 'panth_social_meta/social/og_enabled';

    /**
     * @param OpenGraphResolver $openGraphResolver
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly OpenGraphResolver $openGraphResolver,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Whether Open Graph output is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        try {
            return $this->scopeConfig->isSetFlag(self::XML_OG_ENABLED, ScopeInterface::SCOPE_STORE);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get resolved Open Graph tags if enabled.
     *
     * @return array<string, string>
     */
    public function getTags(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        try {
            return $this->openGraphResolver->resolve();
        } catch (\Throwable) {
            return [];
        }
    }
}
