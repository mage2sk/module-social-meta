<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Block\Head;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\SocialMeta\ViewModel\OpenGraph as OpenGraphViewModel;

/**
 * Head block rendering Open Graph meta tags.
 */
class OpenGraph extends Template
{
    /** @var string */
    protected $_template = 'Panth_SocialMeta::head/opengraph.phtml';

    /**
     * @param Context $context
     * @param OpenGraphViewModel $viewModel
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly OpenGraphViewModel $viewModel,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get resolved Open Graph tags.
     *
     * @return array<string, string>
     */
    public function getTags(): array
    {
        return $this->viewModel->getTags();
    }

    /**
     * Whether Open Graph output is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->viewModel->isEnabled();
    }
}
