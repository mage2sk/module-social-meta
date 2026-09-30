<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Block\Head;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\SocialMeta\ViewModel\TwitterCard as TwitterCardViewModel;

class TwitterCard extends Template
{
    protected $_template = 'Panth_SocialMeta::head/twittercard.phtml';

    public function __construct(
        Context $context,
        private readonly TwitterCardViewModel $viewModel,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getTags(): array
    {
        return $this->viewModel->getTags();
    }

    public function isEnabled(): bool
    {
        return $this->viewModel->isEnabled();
    }
}
