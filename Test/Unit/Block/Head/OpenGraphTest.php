<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Test\Unit\Block\Head;

use Magento\Framework\View\Element\Template\Context;
use Panth\SocialMeta\Block\Head\OpenGraph;
use Panth\SocialMeta\ViewModel\OpenGraph as OpenGraphViewModel;
use PHPUnit\Framework\TestCase;

class OpenGraphTest extends TestCase
{
    public function testBlockDelegatesToViewModel(): void
    {
        $viewModel = $this->createStub(OpenGraphViewModel::class);
        $viewModel->method('getTags')->willReturn(['og:title' => 'T']);
        $viewModel->method('isEnabled')->willReturn(true);

        $block = new OpenGraph($this->createStub(Context::class), $viewModel);

        $this->assertSame(['og:title' => 'T'], $block->getTags());
        $this->assertTrue($block->isEnabled());
        $this->assertSame('Panth_SocialMeta::head/opengraph.phtml', $block->getTemplate());
    }
}
