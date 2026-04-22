<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Block\Head;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\SocialMeta\ViewModel\TwitterCard as TwitterCardViewModel;

/**
 * Head block rendering Twitter Card meta tags.
 */
class TwitterCard extends Template
{
    /** @var string */
    protected $_template = 'Panth_SocialMeta::head/twittercard.phtml';

    /**
     * @param Context $context
     * @param TwitterCardViewModel $viewModel
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly TwitterCardViewModel $viewModel,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get resolved Twitter Card tags.
     *
     * @return array<string, string>
     */
    public function getTags(): array
    {
        return $this->viewModel->getTags();
    }

    /**
     * Whether Twitter Card output is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->viewModel->isEnabled();
    }
}
