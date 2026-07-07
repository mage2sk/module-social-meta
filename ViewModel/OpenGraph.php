<?php
declare(strict_types=1);

namespace Panth\SocialMeta\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Panth\SocialMeta\Model\Social\OpenGraphResolver;

class OpenGraph implements ArgumentInterface
{
    public const XML_OG_ENABLED = 'panth_social_meta/social/og_enabled';

    public function __construct(
        private readonly OpenGraphResolver $openGraphResolver,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(): bool
    {
        try {
            return $this->scopeConfig->isSetFlag(self::XML_OG_ENABLED, ScopeInterface::SCOPE_STORE);
        } catch (\Throwable) {
            return false;
        }
    }

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
