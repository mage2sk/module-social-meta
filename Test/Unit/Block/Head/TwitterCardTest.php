<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Test\Unit\Block\Head;

use Magento\Framework\View\Element\Template\Context;
use Panth\SocialMeta\Block\Head\TwitterCard;
use Panth\SocialMeta\ViewModel\TwitterCard as TwitterCardViewModel;
use PHPUnit\Framework\TestCase;

class TwitterCardTest extends TestCase
{
    public function testBlockDelegatesToViewModel(): void
    {
        $viewModel = $this->createStub(TwitterCardViewModel::class);
        $viewModel->method('getTags')->willReturn(['twitter:card' => 'summary']);
        $viewModel->method('isEnabled')->willReturn(false);

        $block = new TwitterCard($this->createStub(Context::class), $viewModel);

        $this->assertSame(['twitter:card' => 'summary'], $block->getTags());
        $this->assertFalse($block->isEnabled());
        $this->assertSame('Panth_SocialMeta::head/twittercard.phtml', $block->getTemplate());
    }
}
