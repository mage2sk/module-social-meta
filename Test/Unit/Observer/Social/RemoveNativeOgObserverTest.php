<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Test\Unit\Observer\Social;

use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\View\LayoutInterface;
use Panth\SocialMeta\Observer\Social\RemoveNativeOgObserver;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class RemoveNativeOgObserverTest extends TestCase
{
    private function observerWithLayout($layout): Observer
    {
        return new Observer(['event' => new Event(['layout' => $layout])]);
    }

    public function testMissingLayoutIsIgnored(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('debug');

        (new RemoveNativeOgObserver($logger))->execute($this->observerWithLayout(new DataObject()));
        (new RemoveNativeOgObserver($logger))->execute($this->observerWithLayout(null));
    }

    public function testRemovesNativeAndPatternMatchedBlocksOnly(): void
    {
        $present = ['opengraph.general' => true, 'opengraph.cms' => true];
        $removed = [];

        $layout = $this->createStub(LayoutInterface::class);
        $layout->method('getBlock')->willReturnCallback(
            static fn ($name) => isset($present[$name]) ? new DataObject() : false
        );
        $layout->method('getAllBlocks')->willReturn([
            'panth_social_meta.opengraph' => new DataObject(),
            'Custom.OpenGraph.Extra' => new DataObject(),
            'og.meta' => new DataObject(),
            'OG.Product' => new DataObject(),
            'blog.og.sidebar' => new DataObject(),
            'header.links' => new DataObject(),
            123 => new DataObject(),
        ]);
        $layout->method('unsetElement')->willReturnCallback(
            static function ($name) use (&$removed) {
                $removed[] = $name;
                return true;
            }
        );

        $messages = [];
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('debug')->willReturnCallback(
            static function ($message) use (&$messages): void {
                $messages[] = $message;
            }
        );

        (new RemoveNativeOgObserver($logger))->execute($this->observerWithLayout($layout));

        $this->assertSame(
            ['opengraph.general', 'opengraph.cms', 'Custom.OpenGraph.Extra', 'og.meta', 'OG.Product'],
            $removed
        );
        $this->assertCount(5, $messages);
        $this->assertSame(
            '[PanthSocialMeta] Removed native OG block "opengraph.general" from layout.',
            $messages[0]
        );
        $this->assertSame(
            '[PanthSocialMeta] Removed native OG block "og.meta" (pattern match) from layout.',
            $messages[3]
        );
    }

    public function testCleanLayoutTouchesNothing(): void
    {
        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects($this->exactly(4))->method('getBlock')->willReturn(false);
        $layout->expects($this->once())->method('getAllBlocks')->willReturn(['footer' => new DataObject()]);
        $layout->expects($this->never())->method('unsetElement');

        (new RemoveNativeOgObserver($this->createStub(LoggerInterface::class)))
            ->execute($this->observerWithLayout($layout));
    }
}
