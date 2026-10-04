<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Test\Unit\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Panth\SocialMeta\Model\Social\OpenGraphResolver;
use Panth\SocialMeta\ViewModel\OpenGraph;
use PHPUnit\Framework\TestCase;

class OpenGraphTest extends TestCase
{
    private function scopeConfig(?bool $enabled): ScopeConfigInterface
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        if ($enabled === null) {
            $scopeConfig->method('isSetFlag')->willThrowException(new \RuntimeException('config'));
        } else {
            $scopeConfig->method('isSetFlag')->willReturnCallback(
                static fn ($path, $scope = null): bool => $enabled
                    && $path === OpenGraph::XML_OG_ENABLED
                    && $scope === ScopeInterface::SCOPE_STORE
            );
        }
        return $scopeConfig;
    }

    public function testDisabledReturnsNoTagsWithoutResolving(): void
    {
        $resolver = $this->createMock(OpenGraphResolver::class);
        $resolver->expects($this->never())->method('resolve');

        $viewModel = new OpenGraph($resolver, $this->scopeConfig(false));

        $this->assertFalse($viewModel->isEnabled());
        $this->assertSame([], $viewModel->getTags());
    }

    public function testConfigFailureCountsAsDisabled(): void
    {
        $viewModel = new OpenGraph($this->createStub(OpenGraphResolver::class), $this->scopeConfig(null));

        $this->assertFalse($viewModel->isEnabled());
        $this->assertSame([], $viewModel->getTags());
    }

    public function testEnabledReturnsResolvedTags(): void
    {
        $resolver = $this->createStub(OpenGraphResolver::class);
        $resolver->method('resolve')->willReturn(['og:title' => 'Hello']);

        $viewModel = new OpenGraph($resolver, $this->scopeConfig(true));

        $this->assertTrue($viewModel->isEnabled());
        $this->assertSame(['og:title' => 'Hello'], $viewModel->getTags());
    }

    public function testResolverFailureReturnsNoTags(): void
    {
        $resolver = $this->createStub(OpenGraphResolver::class);
        $resolver->method('resolve')->willThrowException(new \Error('broken'));

        $this->assertSame([], (new OpenGraph($resolver, $this->scopeConfig(true)))->getTags());
    }
}
