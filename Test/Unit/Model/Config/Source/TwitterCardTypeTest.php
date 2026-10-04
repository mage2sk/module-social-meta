<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Test\Unit\Model\Config\Source;

use Panth\SocialMeta\Model\Config\Source\TwitterCardType;
use PHPUnit\Framework\TestCase;

class TwitterCardTypeTest extends TestCase
{
    public function testOptionsMatchResolverWhitelist(): void
    {
        $options = (new TwitterCardType())->toOptionArray();

        $this->assertSame(['summary', 'summary_large_image'], array_column($options, 'value'));
        $this->assertSame('Summary', (string) $options[0]['label']);
        $this->assertSame('Summary with Large Image', (string) $options[1]['label']);
    }
}
