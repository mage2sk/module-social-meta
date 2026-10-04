<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class TwitterCardType implements ArrayInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'summary', 'label' => __('Summary')],
            ['value' => 'summary_large_image', 'label' => __('Summary with Large Image')],
        ];
    }
}
