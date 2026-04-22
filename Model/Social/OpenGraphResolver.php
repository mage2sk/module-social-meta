<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Model\Social;

use Magento\Catalog\Model\Product;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\AdvancedSEO\Api\CanonicalResolverInterface;
use Panth\AdvancedSEO\Api\MetaResolverInterface;
use Panth\AdvancedSEO\Api\Data\ResolvedMetaInterface;

/**
 * Resolves Open Graph meta-tag data from the current page context.
 *
 * Pulls canonical URLs and template-rendered meta from Panth_AdvancedSEO's
 * public API interfaces so og:url / og:title / og:description stay in sync
 * with the visible <title> and <meta name="description"> tags.
 */
class OpenGraphResolver
{
    public const XML_DEFAULT_OG_IMAGE = 'panth_social_meta/social/default_og_image';

    /**
     * @param Registry $registry
     * @param StoreManagerInterface $storeManager
     * @param CanonicalResolverInterface $canonicalResolver
     * @param MetaResolverInterface $metaResolver
     * @param ScopeConfigInterface $scopeConfig
     * @param PageConfig|null $pageConfig Injected as Proxy via di.xml.
     */
    public function __construct(
        private readonly Registry $registry,
        private readonly StoreManagerInterface $storeManager,
        private readonly CanonicalResolverInterface $canonicalResolver,
        private readonly MetaResolverInterface $metaResolver,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ?PageConfig $pageConfig = null
    ) {
    }

    /**
     * Resolve Open Graph tags for the current page.
     *
     * @return array<string, string> Keyed by OG property name (e.g. "og:title").
     */
    public function resolve(): array
    {
        try {
            $store = $this->storeManager->getStore();
            $storeId = (int) $store->getId();
        } catch (\Throwable) {
            return [];
        }

        [$entityType, $entityId] = $this->detectEntity();

        // Load the resolved meta for this entity so we can fall back to its
        // title/description when the entity itself has no own value. This
        // guarantees og:title / og:description are populated using the same
        // template-rendered text the page <title> and meta description use.
        $resolvedMeta = $this->loadResolvedMeta($entityType, $entityId, $storeId);

        $tags = [];
        $tags['og:type'] = $this->resolveType($entityType);
        $tags['og:title'] = $this->resolveTitle($entityType, $resolvedMeta);
        $tags['og:description'] = $this->resolveDescription($entityType, $resolvedMeta);
        $tags['og:image'] = $this->resolveImage($entityType);
        $tags['og:url'] = $this->resolveUrl($entityType, $entityId, $storeId);
        $tags['og:site_name'] = $this->resolveSiteName();

        // Facebook Shop / product-catalog ingesters require these on PDP.
        if ($entityType === MetaResolverInterface::ENTITY_PRODUCT) {
            foreach ($this->resolveProductPriceTags() as $property => $value) {
                $tags[$property] = $value;
            }
        }

        return array_filter($tags, static fn (string $v): bool => $v !== '');
    }

    /**
     * Resolve product:price:amount / product:price:currency for the current
     * PDP. Returns an empty array when no current_product is in registry or
     * the final price is non-positive (e.g. typeless/virtual edge cases).
     *
     * @return array<string, string>
     */
    private function resolveProductPriceTags(): array
    {
        $product = $this->registry->registry('current_product');
        if (!$product instanceof Product) {
            return [];
        }

        $finalPrice = $product->getFinalPrice();
        if ($finalPrice === null || $finalPrice === false) {
            try {
                $finalPrice = (float) $product->getPriceInfo()->getPrice('final_price')->getValue();
            } catch (\Throwable) {
                $finalPrice = 0.0;
            }
        }
        $finalPrice = (float) $finalPrice;
        if ($finalPrice <= 0.0) {
            return [];
        }

        try {
            $currency = (string) $this->storeManager->getStore()->getCurrentCurrencyCode();
        } catch (\Throwable) {
            $currency = '';
        }
        if ($currency === '') {
            return [];
        }

        return [
            'product:price:amount'   => number_format($finalPrice, 2, '.', ''),
            'product:price:currency' => $currency,
        ];
    }

    /**
     * Load the resolved meta DTO for the current entity, or null if none.
     *
     * @param string|null $entityType
     * @param int $entityId
     * @param int $storeId
     * @return ResolvedMetaInterface|null
     */
    private function loadResolvedMeta(?string $entityType, int $entityId, int $storeId): ?ResolvedMetaInterface
    {
        if ($entityType === null || $entityId === 0) {
            return null;
        }
        try {
            return $this->metaResolver->resolve($entityType, $entityId, $storeId);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Map entity type to OG type value.
     *
     * @param string|null $entityType
     * @return string
     */
    private function resolveType(?string $entityType): string
    {
        return match ($entityType) {
            MetaResolverInterface::ENTITY_PRODUCT => 'product',
            MetaResolverInterface::ENTITY_CMS => 'article',
            default => 'website',
        };
    }

    /**
     * Resolve title from current entity or page config.
     *
     * Falls back through: explicit entity meta_title -> resolved meta from
     * MetaResolver (template-rendered) -> entity name -> store name.
     *
     * @param string|null $entityType
     * @param ResolvedMetaInterface|null $resolvedMeta
     * @return string
     */
    private function resolveTitle(?string $entityType, ?ResolvedMetaInterface $resolvedMeta): string
    {
        $product = $this->registry->registry('current_product');
        if ($entityType === MetaResolverInterface::ENTITY_PRODUCT && $product instanceof Product) {
            $own = (string) $product->getMetaTitle();
            if ($own !== '') {
                return $own;
            }
            if ($resolvedMeta !== null && (string) $resolvedMeta->getMetaTitle() !== '') {
                return (string) $resolvedMeta->getMetaTitle();
            }
            return (string) $product->getName();
        }

        $category = $this->registry->registry('current_category');
        if ($entityType === MetaResolverInterface::ENTITY_CATEGORY && $category !== null) {
            $own = (string) $category->getMetaTitle();
            if ($own !== '') {
                return $own;
            }
            if ($resolvedMeta !== null && (string) $resolvedMeta->getMetaTitle() !== '') {
                return (string) $resolvedMeta->getMetaTitle();
            }
            return (string) $category->getName();
        }

        $cmsPage = $this->registry->registry('cms_page');
        if ($entityType === MetaResolverInterface::ENTITY_CMS && $cmsPage instanceof CmsPage) {
            $own = (string) $cmsPage->getMetaTitle();
            if ($own !== '') {
                return $own;
            }
            if ($resolvedMeta !== null && (string) $resolvedMeta->getMetaTitle() !== '') {
                return (string) $resolvedMeta->getMetaTitle();
            }
            return (string) $cmsPage->getTitle();
        }

        // Fallback for pages without a catalog/CMS entity (e.g. custom
        // controllers such as Panth_Testimonials, Panth_Faq, static routes):
        // prefer the page title set on the PageConfig by the controller so
        // the og:title reflects the actual page rather than the store name.
        $pageTitle = $this->getPageConfigTitle();
        if ($pageTitle !== '') {
            return $pageTitle;
        }
        try {
            return (string) $this->storeManager->getStore()->getName();
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Read the current page title from PageConfig, if a controller/plugin
     * has already set one.
     *
     * @return string
     */
    private function getPageConfigTitle(): string
    {
        if ($this->pageConfig === null) {
            return '';
        }
        try {
            $title = (string) $this->pageConfig->getTitle()->get();
        } catch (\Throwable) {
            return '';
        }
        return trim($title);
    }

    /**
     * Read the current meta description from PageConfig, if a controller/
     * plugin has already set one.
     *
     * @return string
     */
    private function getPageConfigDescription(): string
    {
        if ($this->pageConfig === null) {
            return '';
        }
        try {
            $desc = (string) $this->pageConfig->getDescription();
        } catch (\Throwable) {
            return '';
        }
        return trim($desc);
    }

    /**
     * Resolve meta description from current entity.
     *
     * Falls back through: explicit entity meta_description -> resolved meta
     * from MetaResolver (template-rendered with current store context) ->
     * entity description -> store default description.
     *
     * @param string|null $entityType
     * @param ResolvedMetaInterface|null $resolvedMeta
     * @return string
     */
    private function resolveDescription(?string $entityType, ?ResolvedMetaInterface $resolvedMeta): string
    {
        $product = $this->registry->registry('current_product');
        if ($entityType === MetaResolverInterface::ENTITY_PRODUCT && $product instanceof Product) {
            $own = (string) $product->getMetaDescription();
            if ($own !== '') {
                return $this->truncate($own, 200);
            }
            if ($resolvedMeta !== null && (string) $resolvedMeta->getMetaDescription() !== '') {
                return $this->truncate((string) $resolvedMeta->getMetaDescription(), 200);
            }
            return $this->truncate((string) $product->getShortDescription(), 200);
        }

        $category = $this->registry->registry('current_category');
        if ($entityType === MetaResolverInterface::ENTITY_CATEGORY && $category !== null) {
            $own = (string) $category->getMetaDescription();
            if ($own !== '') {
                return $this->truncate($own, 200);
            }
            if ($resolvedMeta !== null && (string) $resolvedMeta->getMetaDescription() !== '') {
                return $this->truncate((string) $resolvedMeta->getMetaDescription(), 200);
            }
            return $this->truncate((string) $category->getDescription(), 200);
        }

        $cmsPage = $this->registry->registry('cms_page');
        if ($cmsPage instanceof CmsPage) {
            $own = (string) $cmsPage->getMetaDescription();
            if ($own !== '') {
                return $this->truncate($own, 200);
            }
            if ($resolvedMeta !== null && (string) $resolvedMeta->getMetaDescription() !== '') {
                return $this->truncate((string) $resolvedMeta->getMetaDescription(), 200);
            }
            return '';
        }

        $pageDesc = $this->getPageConfigDescription();
        if ($pageDesc !== '') {
            return $this->truncate($pageDesc, 200);
        }
        try {
            $store = $this->storeManager->getStore();
            $defaultDesc = $store->getConfig('design/head/default_description');
            return $defaultDesc ? $this->truncate((string) $defaultDesc, 200) : '';
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Resolve image URL with progressive fallbacks:
     *   product image -> category image -> first product in category -> store logo
     *   -> Magento default product placeholder -> empty.
     *
     * @param string|null $entityType
     * @return string
     */
    private function resolveImage(?string $entityType): string
    {
        try {
            $product = $this->registry->registry('current_product');
            if ($entityType === MetaResolverInterface::ENTITY_PRODUCT && $product instanceof Product) {
                $image = $product->getImage();
                if ($image && $image !== 'no_selection') {
                    $store = $this->storeManager->getStore();
                    $mediaUrl = rtrim((string) $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA), '/');
                    return $mediaUrl . '/catalog/product' . $image;
                }
            }

            $category = $this->registry->registry('current_category');
            if ($entityType === MetaResolverInterface::ENTITY_CATEGORY && $category !== null) {
                $categoryImage = $category->getImageUrl();
                if ($categoryImage) {
                    return (string) $categoryImage;
                }
                $firstProductImage = $this->getFirstProductImageInCategory((int) $category->getId());
                if ($firstProductImage !== '') {
                    return $firstProductImage;
                }
            }

            $defaultOgImage = $this->getDefaultOgImageUrl();
            if ($defaultOgImage !== '') {
                return $defaultOgImage;
            }

            $logo = $this->getStoreLogoUrl();
            if ($logo !== '') {
                return $logo;
            }

            return $this->getPlaceholderImageUrl();
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Get the URL of the first product image in the given category.
     *
     * @param int $categoryId
     * @return string
     */
    private function getFirstProductImageInCategory(int $categoryId): string
    {
        try {
            $category = $this->registry->registry('current_category');
            if ($category === null || (int) $category->getId() !== $categoryId) {
                return '';
            }
            $productCollection = $category->getProductCollection();
            if ($productCollection === null) {
                return '';
            }
            $productCollection->addAttributeToSelect('image')
                ->addFieldToFilter('image', ['notnull' => true])
                ->addFieldToFilter('image', ['neq' => 'no_selection'])
                ->setPageSize(1)
                ->setCurPage(1);
            foreach ($productCollection as $product) {
                $image = $product->getImage();
                if ($image && $image !== 'no_selection') {
                    $store = $this->storeManager->getStore();
                    $mediaUrl = rtrim((string) $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA), '/');
                    return $mediaUrl . '/catalog/product' . $image;
                }
            }
        } catch (\Throwable) {
            // intentionally empty
        }
        return '';
    }

    /**
     * Get the admin-configured default OG image URL.
     *
     * The stored value is a media-relative path saved by the Image backend
     * under `panth_seo/og/`. We reject any value containing path-traversal
     * sequences and return an absolute media URL when safe.
     *
     * @return string
     */
    private function getDefaultOgImageUrl(): string
    {
        try {
            $value = $this->scopeConfig->getValue(self::XML_DEFAULT_OG_IMAGE, ScopeInterface::SCOPE_STORE);
            if ($value === null || $value === '') {
                return '';
            }
            $relative = (string) $value;
            if (str_contains($relative, '..')
                || str_contains($relative, "\0")
                || str_contains($relative, '\\')
                || str_starts_with($relative, '/')) {
                return '';
            }
            if (preg_match('#^https?://#i', $relative) === 1) {
                return $relative;
            }
            $store = $this->storeManager->getStore();
            $mediaUrl = rtrim((string) $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA), '/');
            return $mediaUrl . '/' . ltrim($relative, '/');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Get the Magento default product placeholder image URL as the last resort.
     *
     * @return string
     */
    private function getPlaceholderImageUrl(): string
    {
        try {
            $store = $this->storeManager->getStore();
            $mediaUrl = rtrim((string) $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA), '/');
            $placeholder = $store->getConfig('catalog/placeholder/image_placeholder');
            if ($placeholder) {
                return $mediaUrl . '/catalog/product/placeholder/' . ltrim((string) $placeholder, '/');
            }
            return $mediaUrl . '/catalog/product/placeholder/default/image.jpg';
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Resolve canonical URL for the current entity.
     *
     * @param string|null $entityType
     * @param int $entityId
     * @param int $storeId
     * @return string
     */
    private function resolveUrl(?string $entityType, int $entityId, int $storeId): string
    {
        if ($entityType === null || $entityId === 0) {
            try {
                $currentUrl = (string) $this->storeManager->getStore()->getCurrentUrl(false);
                return $this->canonicalResolver->normalize($currentUrl, $storeId);
            } catch (\Throwable) {
                return '';
            }
        }

        try {
            return $this->canonicalResolver->getCanonicalUrl($entityType, $entityId, $storeId);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Resolve store name from configuration.
     *
     * @return string
     */
    private function resolveSiteName(): string
    {
        try {
            return (string) $this->storeManager->getStore()->getName();
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Get the store logo URL from theme configuration.
     *
     * @return string
     */
    private function getStoreLogoUrl(): string
    {
        try {
            $store = $this->storeManager->getStore();
            $logoSrc = $store->getConfig('design/header/logo_src');
            if ($logoSrc) {
                $mediaUrl = rtrim((string) $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA), '/');
                return $mediaUrl . '/logo/' . ltrim((string) $logoSrc, '/');
            }
        } catch (\Throwable) {
            // intentionally empty
        }

        return '';
    }

    /**
     * Detect the current entity type and ID from the registry.
     *
     * @return array{0: ?string, 1: int}
     */
    private function detectEntity(): array
    {
        $product = $this->registry->registry('current_product');
        if ($product !== null && $product->getId()) {
            return [MetaResolverInterface::ENTITY_PRODUCT, (int) $product->getId()];
        }

        $category = $this->registry->registry('current_category');
        if ($category !== null && $category->getId()) {
            return [MetaResolverInterface::ENTITY_CATEGORY, (int) $category->getId()];
        }

        $cmsPage = $this->registry->registry('cms_page');
        if ($cmsPage !== null && $cmsPage->getId()) {
            return [MetaResolverInterface::ENTITY_CMS, (int) $cmsPage->getId()];
        }

        return [null, 0];
    }

    /**
     * Truncate a string to max length, stripping HTML tags first.
     *
     * @param string $text
     * @param int $maxLength
     * @return string
     */
    private function truncate(string $text, int $maxLength): string
    {
        $text = trim(strip_tags($text));
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 3) . '...';
    }
}
