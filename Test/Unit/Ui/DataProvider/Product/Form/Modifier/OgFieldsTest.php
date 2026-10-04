<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Test\Unit\Ui\DataProvider\Product\Form\Modifier;

use Magento\Framework\Stdlib\ArrayManager;
use Panth\SocialMeta\Ui\DataProvider\Product\Form\Modifier\OgFields;
use PHPUnit\Framework\TestCase;

class OgFieldsTest extends TestCase
{
    private function field(int $sortOrder): array
    {
        return ['arguments' => ['data' => ['config' => ['sortOrder' => $sortOrder, 'notice' => null]]]];
    }

    private function container(string $code, int $sortOrder): array
    {
        return [
            'arguments' => ['data' => ['config' => ['sortOrder' => $sortOrder]]],
            'children' => [$code => $this->field($sortOrder)],
        ];
    }

    private function meta(): array
    {
        return [
            'search-engine-optimization' => [
                'children' => [
                    'container_meta_description' => $this->container('meta_description', 50),
                    'container_og_image' => $this->container('og_image', 60),
                    'container_og_description' => $this->container('og_description', 70),
                    'container_og_title' => $this->container('og_title', 80),
                    'container_seo_name' => $this->container('seo_name', 90),
                ],
            ],
        ];
    }

    private function config(array $meta, string $container, ?string $field = null): array
    {
        $node = $meta['search-engine-optimization']['children'][$container];
        if ($field !== null) {
            $node = $node['children'][$field];
        }
        return $node['arguments']['data']['config'];
    }

    public function testFieldsAreOrderedTitleDescriptionImageAfterMetaDescription(): void
    {
        $meta = (new OgFields(new ArrayManager()))->modifyMeta($this->meta());

        $this->assertSame(51, $this->config($meta, 'container_og_title')['sortOrder']);
        $this->assertSame(52, $this->config($meta, 'container_og_description')['sortOrder']);
        $this->assertSame(53, $this->config($meta, 'container_og_image')['sortOrder']);
        $this->assertSame(51, $this->config($meta, 'container_og_title', 'og_title')['sortOrder']);
        $this->assertSame(90, $this->config($meta, 'container_seo_name')['sortOrder']);
        $this->assertSame(50, $this->config($meta, 'container_meta_description')['sortOrder']);
    }

    public function testFieldsGetHelpNotices(): void
    {
        $meta = (new OgFields(new ArrayManager()))->modifyMeta($this->meta());

        $this->assertStringContainsString(
            'meta title',
            $this->config($meta, 'container_og_title', 'og_title')['notice']
        );
        $this->assertStringContainsString(
            '200 characters',
            $this->config($meta, 'container_og_description', 'og_description')['notice']
        );
        $this->assertStringContainsString(
            '1200 x 630',
            $this->config($meta, 'container_og_image', 'og_image')['notice']
        );
    }

    public function testMissingFieldsAndMetaDescriptionAreTolerated(): void
    {
        $meta = [
            'search-engine-optimization' => [
                'children' => ['container_og_title' => $this->container('og_title', 80)],
            ],
        ];

        $result = (new OgFields(new ArrayManager()))->modifyMeta($meta);

        $this->assertSame(1, $this->config($result, 'container_og_title')['sortOrder']);
        $this->assertArrayNotHasKey('container_og_image', $result['search-engine-optimization']['children']);
    }

    public function testFieldOutsideContainerKeepsParentUntouched(): void
    {
        $meta = [
            'seo' => [
                'arguments' => ['data' => ['config' => ['sortOrder' => 7]]],
                'children' => ['meta_description' => $this->field(20), 'og_title' => $this->field(5)],
            ],
        ];

        $result = (new OgFields(new ArrayManager()))->modifyMeta($meta);

        $this->assertSame(21, $result['seo']['children']['og_title']['arguments']['data']['config']['sortOrder']);
        $this->assertSame(7, $result['seo']['arguments']['data']['config']['sortOrder']);
    }

    public function testEmptyMetaAndDataPassThrough(): void
    {
        $modifier = new OgFields(new ArrayManager());

        $this->assertSame([], $modifier->modifyMeta([]));
        $this->assertSame(['5' => ['product' => ['sku' => 'A']]], $modifier->modifyData(['5' => ['product' => ['sku' => 'A']]]));
    }
}
