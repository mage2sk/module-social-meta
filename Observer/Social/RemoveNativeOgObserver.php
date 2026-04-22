<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Observer\Social;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * Observer on `layout_generate_blocks_after` (frontend area only).
 *
 * Removes native Magento / Hyva Open Graph blocks from the layout so that
 * duplicate OG meta tags are never rendered when our module's own OG output
 * is active. This runs once per layout generation (FPC-cached afterwards).
 */
class RemoveNativeOgObserver implements ObserverInterface
{
    private const XML_OG_ENABLED = 'panth_social_meta/social/og_enabled';

    /**
     * Well-known native OG block names that Magento core and Hyva may add
     * via layout XML. Each name is checked with {@see LayoutInterface::getBlock()}
     * before removal to avoid warnings on layouts that do not include them.
     *
     * @var string[]
     */
    private const NATIVE_OG_BLOCKS = [
        'opengraph.general',
        'opengraph.product',
        'opengraph.category',
        'opengraph.cms',
    ];

    /**
     * Name fragments that identify dynamically-named OG blocks. `opengraph`
     * is matched as a substring; `og.` is matched as a prefix so it does not
     * false-match blocks such as `catalog.*` which happen to contain `"og."`.
     *
     * The own blocks registered by Panth_SocialMeta are named
     * `panth_social_meta.opengraph` and `panth_social_meta.twittercard`; the
     * `opengraph` substring would match the former, so an explicit allow-list
     * check (block name prefix) excludes our own blocks from removal.
     *
     * @var string[]
     */
    private const OG_NAME_PATTERNS = [
        'opengraph',
        'og.',
    ];

    /**
     * Block-name prefix that identifies blocks belonging to this module and
     * must never be removed by pattern matching.
     */
    private const OWN_BLOCK_PREFIX = 'panth_social_meta.';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Remove native OpenGraph blocks when OG output is enabled.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if (!$this->isOgEnabled()) {
            return;
        }

        /** @var LayoutInterface $layout */
        $layout = $observer->getEvent()->getLayout();
        if (!$layout instanceof LayoutInterface) {
            return;
        }

        $this->removeWellKnownBlocks($layout);
        $this->removeByPattern($layout);
    }

    /**
     * Remove blocks whose names are explicitly listed.
     *
     * @param LayoutInterface $layout
     * @return void
     */
    private function removeWellKnownBlocks(LayoutInterface $layout): void
    {
        foreach (self::NATIVE_OG_BLOCKS as $blockName) {
            if ($layout->getBlock($blockName)) {
                $layout->unsetElement($blockName);
                $this->logger->debug(
                    sprintf('[PanthSocialMeta] Removed native OG block "%s" from layout.', $blockName)
                );
            }
        }
    }

    /**
     * Scan all layout element names for OG-related substrings and remove them.
     *
     * @param LayoutInterface $layout
     * @return void
     */
    private function removeByPattern(LayoutInterface $layout): void
    {
        /** @var string[] $allNames */
        $allNames = $layout->getAllBlocks();

        foreach ($allNames as $name => $block) {
            $nameStr = (string) $name;
            // Never unset this module's own blocks.
            if (str_starts_with($nameStr, self::OWN_BLOCK_PREFIX)) {
                continue;
            }

            $lowerName = strtolower($nameStr);

            foreach (self::OG_NAME_PATTERNS as $pattern) {
                $isMatch = $pattern === 'og.'
                    ? str_starts_with($lowerName, 'og.')
                    : str_contains($lowerName, $pattern);
                if ($isMatch) {
                    $layout->unsetElement($nameStr);
                    $this->logger->debug(
                        sprintf('[PanthSocialMeta] Removed native OG block "%s" (pattern match) from layout.', $nameStr)
                    );
                    break;
                }
            }
        }
    }

    /**
     * Check whether OG tag emission is enabled for the current scope.
     *
     * @return bool
     */
    private function isOgEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_OG_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }
}
