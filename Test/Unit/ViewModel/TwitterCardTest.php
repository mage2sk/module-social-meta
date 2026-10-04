<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Test\Unit\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Panth\SocialMeta\Model\Social\TwitterCardResolver;
use Panth\SocialMeta\ViewModel\TwitterCard;
use PHPUnit\Framework\TestCase;

class TwitterCardTest extends TestCase
{
    private function scopeConfig(?bool $enabled): ScopeConfigInterface
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        if ($enabled === null) {
            $scopeConfig->method('isSetFlag')->willThrowException(new \RuntimeException('config'));
        } else {
            $scopeConfig->method('isSetFlag')->willReturnCallback(
                static fn ($path, $scope = null): bool => $enabled
                    && $path === TwitterCard::XML_TWITTER_ENABLED
                    && $scope === ScopeInterface::SCOPE_STORE
            );
        }
        return $scopeConfig;
    }

    public function testDisabledReturnsNoTagsWithoutResolving(): void
    {
        $resolver = $this->createMock(TwitterCardResolver::class);
        $resolver->expects($this->never())->method('resolve');

        $viewModel = new TwitterCard($resolver, $this->scopeConfig(false));

        $this->assertFalse($viewModel->isEnabled());
        $this->assertSame([], $viewModel->getTags());
    }

    public function testConfigFailureCountsAsDisabled(): void
    {
        $viewModel = new TwitterCard($this->createStub(TwitterCardResolver::class), $this->scopeConfig(null));

        $this->assertFalse($viewModel->isEnabled());
        $this->assertSame([], $viewModel->getTags());
    }

    public function testEnabledReturnsResolvedTags(): void
    {
        $resolver = $this->createStub(TwitterCardResolver::class);
        $resolver->method('resolve')->willReturn(['twitter:card' => 'summary']);

        $viewModel = new TwitterCard($resolver, $this->scopeConfig(true));

        $this->assertTrue($viewModel->isEnabled());
        $this->assertSame(['twitter:card' => 'summary'], $viewModel->getTags());
    }

    public function testResolverFailureReturnsNoTags(): void
    {
        $resolver = $this->createStub(TwitterCardResolver::class);
        $resolver->method('resolve')->willThrowException(new \RuntimeException('broken'));

        $this->assertSame([], (new TwitterCard($resolver, $this->scopeConfig(true)))->getTags());
    }
}
