<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Setup\Patch\Data;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Install product and category OG attributes (og_title, og_description,
 * og_image) under the "Search Engine Optimization" group on every attribute
 * set. Depends on AdvancedSEO's AddSeoNameAttribute so the SEO group exists
 * with seo_name already attached.
 */
class AddOgAttributes implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    /**
     * Apply the data patch.
     *
     * @return self
     */
    public function apply(): self
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $ogAttributes = [
            'og_title' => ['label' => 'OG Title', 'type' => 'varchar', 'input' => 'text', 'sort' => 60],
            'og_description' => ['label' => 'OG Description', 'type' => 'text', 'input' => 'textarea', 'sort' => 65],
            'og_image' => ['label' => 'OG Image URL', 'type' => 'varchar', 'input' => 'text', 'sort' => 70],
        ];

        // Product OG attributes
        foreach ($ogAttributes as $code => $config) {
            if (!$eavSetup->getAttributeId(Product::ENTITY, $code)) {
                $eavSetup->addAttribute(Product::ENTITY, $code, [
                    'type' => $config['type'],
                    'label' => $config['label'],
                    'input' => $config['input'],
                    'required' => false,
                    'global' => ScopedAttributeInterface::SCOPE_STORE,
                    'visible' => true,
                    'user_defined' => false,
                    'searchable' => false,
                    'filterable' => false,
                    'comparable' => false,
                    'visible_on_front' => false,
                    'used_in_product_listing' => false,
                    'sort_order' => $config['sort'],
                    'group' => 'Search Engine Optimization',
                ]);
            }
        }

        // Assign product OG attributes to ALL attribute sets
        $productEntityTypeId = $eavSetup->getEntityTypeId(Product::ENTITY);
        $conn = $this->moduleDataSetup->getConnection();
        $attrSets = $conn->fetchCol(
            'SELECT attribute_set_id FROM eav_attribute_set WHERE entity_type_id = ?',
            [$productEntityTypeId]
        );

        $seoGroupName = 'Search Engine Optimization';
        foreach ($attrSets as $setId) {
            $groupId = $eavSetup->getAttributeGroupId($productEntityTypeId, $setId, $seoGroupName);
            if (!$groupId) {
                $eavSetup->addAttributeGroup($productEntityTypeId, $setId, $seoGroupName, 65);
                $groupId = $eavSetup->getAttributeGroupId($productEntityTypeId, $setId, $seoGroupName);
            }

            foreach (array_keys($ogAttributes) as $attrCode) {
                $attrId = $eavSetup->getAttributeId($productEntityTypeId, $attrCode);
                if ($attrId) {
                    $eavSetup->addAttributeToGroup($productEntityTypeId, $setId, $groupId, $attrId);
                }
            }
        }

        // Category OG attributes
        foreach ($ogAttributes as $code => $config) {
            if (!$eavSetup->getAttributeId(Category::ENTITY, $code)) {
                $eavSetup->addAttribute(Category::ENTITY, $code, [
                    'type' => $config['type'],
                    'label' => $config['label'],
                    'input' => $config['input'],
                    'required' => false,
                    'global' => ScopedAttributeInterface::SCOPE_STORE,
                    'visible' => true,
                    'sort_order' => $config['sort'],
                    'group' => 'Search Engine Optimization',
                ]);
            }
        }

        return $this;
    }

    /**
     * List patch dependencies.
     *
     * @return array<int, string>
     */
    public static function getDependencies(): array
    {
        return [
            // Ensure AdvancedSEO has created the SEO group + seo_name first
            // so our attributes land in the correct group/position.
            \Panth\AdvancedSEO\Setup\Patch\Data\AddSeoNameAttribute::class,
        ];
    }

    /**
     * List historical patch aliases.
     *
     * @return array<int, string>
     */
    public function getAliases(): array
    {
        return [
            // Accept the original AdvancedSEO-namespaced class as an alias so
            // installs that have already applied the AdvancedSEO patch do not
            // re-run this patch after the split.
            \Panth\AdvancedSEO\Setup\Patch\Data\AddOgAttributes::class,
        ];
    }
}
