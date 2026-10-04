<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Framework\Stdlib\ArrayManager;

class OgFields extends AbstractModifier
{
    public const FIELDS = ['og_title', 'og_description', 'og_image'];

    public function __construct(
        private readonly ArrayManager $arrayManager
    ) {
    }

    public function modifyData(array $data)
    {
        return $data;
    }

    public function modifyMeta(array $meta)
    {
        $baseSortOrder = $this->getBaseSortOrder($meta);
        $notices = $this->getNotices();

        foreach (self::FIELDS as $index => $code) {
            $path = $this->arrayManager->findPath($code, $meta, null, 'children');
            if ($path === null) {
                continue;
            }
            $meta = $this->arrayManager->merge(
                $path . self::META_CONFIG_PATH,
                $meta,
                [
                    'notice' => $notices[$code],
                    'sortOrder' => $baseSortOrder + $index + 1,
                ]
            );
            $containerPath = $this->arrayManager->slicePath($path, 0, -2);
            if ($containerPath !== ''
                && str_starts_with((string) $this->arrayManager->slicePath($containerPath, -1), 'container_')
            ) {
                $meta = $this->arrayManager->merge(
                    $containerPath . self::META_CONFIG_PATH,
                    $meta,
                    ['sortOrder' => $baseSortOrder + $index + 1]
                );
            }
        }

        return $meta;
    }

    private function getBaseSortOrder(array $meta): int
    {
        foreach (['container_meta_description', 'meta_description'] as $code) {
            $path = $this->arrayManager->findPath($code, $meta, null, 'children');
            if ($path === null) {
                continue;
            }
            $sortOrder = $this->arrayManager->get($path . self::META_CONFIG_PATH . '/sortOrder', $meta);
            if (is_numeric($sortOrder)) {
                return (int) $sortOrder;
            }
        }

        return 0;
    }

    private function getNotices(): array
    {
        return [
            'og_title' => (string) __(
                'Title shown when this product is shared on social networks. '
                . 'Leave empty to use the meta title or the product name.'
            ),
            'og_description' => (string) __(
                'Description shown in social previews. Leave empty to use the meta description or the short '
                . 'description. The first 200 characters are used.'
            ),
            'og_image' => (string) __(
                'Absolute https:// URL or a path relative to the media folder, for example '
                . 'catalog/product/a/b/share.jpg. Recommended size 1200 x 630 px. '
                . 'Leave empty to use the base image.'
            ),
        ];
    }
}
