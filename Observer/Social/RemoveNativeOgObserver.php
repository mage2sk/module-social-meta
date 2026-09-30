<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Observer\Social;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\LayoutInterface;
use Psr\Log\LoggerInterface;

class RemoveNativeOgObserver implements ObserverInterface
{
    private const NATIVE_OG_BLOCKS = [
        'opengraph.general',
        'opengraph.product',
        'opengraph.category',
        'opengraph.cms',
    ];

    private const OG_NAME_PATTERNS = [
        'opengraph',
        'og.',
    ];

    private const OWN_BLOCK_PREFIX = 'panth_social_meta.';

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $layout = $observer->getEvent()->getLayout();
        if (!$layout instanceof LayoutInterface) {
            return;
        }

        $this->removeWellKnownBlocks($layout);
        $this->removeByPattern($layout);
    }

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

    private function removeByPattern(LayoutInterface $layout): void
    {
        $allNames = $layout->getAllBlocks();

        foreach ($allNames as $name => $block) {
            $nameStr = (string) $name;

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
}
