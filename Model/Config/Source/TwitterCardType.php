<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Source model for Twitter Card type dropdown in system configuration.
 */
class TwitterCardType implements ArrayInterface
{
    /**
     * Return Twitter Card type options for the admin dropdown.
     *
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'summary', 'label' => __('Summary')],
            ['value' => 'summary_large_image', 'label' => __('Summary with Large Image')],
        ];
    }
}
