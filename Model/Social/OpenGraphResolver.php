<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Model\Social;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Resolves Open Graph meta-tag data from the current page context.
 *
 * NOTE: after the AdvancedSEO split this module is self-contained and
 * no longer depends on Panth\AdvancedSEO\* API interfaces. Canonical
 * URLs are built locally from the entity's own URL model, and meta
 * title/description fall back to the entity's native getMetaTitle() /
 * getMetaDescription() plus the PageConfig title/description set by
 * the controller. Advanced template-rendered meta (rule-engine, template
 * precedence) that lived in Panth_AdvancedSEO is no longer used here.
 */
class OpenGraphResolver
{
    public const ENTITY_PRODUCT  = 'product';
    public const ENTITY_CATEGORY = 'category';
    public const ENTITY_CMS      = 'cms';

    public const XML_DEFAULT_OG_IMAGE = 'panth_social_meta/social/default_og_image';

    /**
     * @param Registry $registry
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param PageConfig|null $pageConfig Injected as Proxy via di.xml.
     */
    public function __construct(
        private readonly Registry $registry,
        private readonly StoreManagerInterface $storeManager,
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

        $tags = [];
        $tags['og:type'] = $this->resolveType($entityType);
        $tags['og:title'] = $this->resolveTitle($entityType);
        $tags['og:description'] = $this->resolveDescription($entityType);
        $tags['og:image'] = $this->resolveImage($entityType);
        $tags['og:url'] = $this->resolveUrl($entityType, $entityId, $storeId);
        $tags['og:site_name'] = $this->resolveSiteName();

        // Facebook Shop / product-catalog ingesters require these on PDP.
        if ($entityType === self::ENTITY_PRODUCT) {
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
     * Map entity type to OG type value.
     *
     * @param string|null $entityType
     * @return string
     */
    private function resolveType(?string $entityType): string
    {
        return match ($entityType) {
            self::ENTITY_PRODUCT => 'product',
            self::ENTITY_CMS => 'article',
            default => 'website',
        };
    }

    /**
     * Resolve title from current entity or page config.
     *
     * Fallback chain: entity own meta_title -> entity name/title -> page
     * config title set by controller -> store name. Template-rendered
     * meta (previously sourced from Panth_AdvancedSEO's MetaResolver) is
     * no longer available after the split.
     *
     * @param string|null $entityType
     * @return string
     */
    private function resolveTitle(?string $entityType): string
    {
        $product = $this->registry->registry('current_product');
        if ($entityType === self::ENTITY_PRODUCT && $product instanceof Product) {
            $own = (string) $product->getMetaTitle();
            if ($own !== '') {
                return $own;
            }
            return (string) $product->getName();
        }

        $category = $this->registry->registry('current_category');
        if ($entityType === self::ENTITY_CATEGORY && $category !== null) {
            $own = (string) $category->getMetaTitle();
            if ($own !== '') {
                return $own;
            }
            return (string) $category->getName();
        }

        $cmsPage = $this->registry->registry('cms_page');
        if ($entityType === self::ENTITY_CMS && $cmsPage instanceof CmsPage) {
            $own = (string) $cmsPage->getMetaTitle();
            if ($own !== '') {
                return $own;
            }
            return (string) $cmsPage->getTitle();
        }

        // Fallback for pages without a catalog/CMS entity (e.g. custom
        // controllers): prefer the page title set on PageConfig so
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
     * Fallback chain: entity own meta_description -> product short
     * description / category description -> page config description ->
     * store default description. Template-rendered meta from Panth_
     * AdvancedSEO's MetaResolver is no longer consulted after the split.
     *
     * @param string|null $entityType
     * @return string
     */
    private function resolveDescription(?string $entityType): string
    {
        $product = $this->registry->registry('current_product');
        if ($entityType === self::ENTITY_PRODUCT && $product instanceof Product) {
            $own = (string) $product->getMetaDescription();
            if ($own !== '') {
                return $this->truncate($own, 200);
            }
            return $this->truncate((string) $product->getShortDescription(), 200);
        }

        $category = $this->registry->registry('current_category');
        if ($entityType === self::ENTITY_CATEGORY && $category !== null) {
            $own = (string) $category->getMetaDescription();
            if ($own !== '') {
                return $this->truncate($own, 200);
            }
            return $this->truncate((string) $category->getDescription(), 200);
        }

        $cmsPage = $this->registry->registry('cms_page');
        if ($cmsPage instanceof CmsPage) {
            $own = (string) $cmsPage->getMetaDescription();
            if ($own !== '') {
                return $this->truncate($own, 200);
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
     *   product image -> category image -> first product in category -> default OG image
     *   -> store logo -> Magento default product placeholder -> empty.
     *
     * @param string|null $entityType
     * @return string
     */
    private function resolveImage(?string $entityType): string
    {
        try {
            $product = $this->registry->registry('current_product');
            if ($entityType === self::ENTITY_PRODUCT && $product instanceof Product) {
                $image = $product->getImage();
                if ($image && $image !== 'no_selection') {
                    $store = $this->storeManager->getStore();
                    $mediaUrl = rtrim((string) $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/');
                    return $mediaUrl . '/catalog/product' . $image;
                }
            }

            $category = $this->registry->registry('current_category');
            if ($entityType === self::ENTITY_CATEGORY && $category !== null) {
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
                    $mediaUrl = rtrim((string) $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/');
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
     * under `panth_social_meta/social/`. We reject any value containing
     * path-traversal sequences and return an absolute media URL when safe.
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
            $mediaUrl = rtrim((string) $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/');
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
            $mediaUrl = rtrim((string) $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/');
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
     * Previously delegated to Panth_AdvancedSEO's CanonicalResolver which
     * honoured custom-canonical overrides and parameter strip rules. After
     * the split we build a minimal canonical locally from the entity's URL
     * model; pages without a known entity fall back to the current URL with
     * its query string stripped.
     *
     * @param string|null $entityType
     * @param int $entityId
     * @param int $storeId
     * @return string
     */
    private function resolveUrl(?string $entityType, int $entityId, int $storeId): string
    {
        try {
            if ($entityType === self::ENTITY_PRODUCT) {
                $product = $this->registry->registry('current_product');
                if ($product instanceof Product) {
                    $url = (string) $product->getProductUrl(false);
                    if ($url !== '') {
                        return $this->stripQuery($url);
                    }
                }
            }

            if ($entityType === self::ENTITY_CATEGORY) {
                $category = $this->registry->registry('current_category');
                if ($category instanceof Category) {
                    $url = (string) $category->getUrl();
                    if ($url !== '') {
                        return $this->stripQuery($url);
                    }
                }
            }

            if ($entityType === self::ENTITY_CMS) {
                $cmsPage = $this->registry->registry('cms_page');
                if ($cmsPage instanceof CmsPage) {
                    try {
                        $store = $this->storeManager->getStore();
                        $base = rtrim((string) $store->getBaseUrl(), '/');
                        $identifier = ltrim((string) $cmsPage->getIdentifier(), '/');
                        if ($identifier !== '') {
                            return $base . '/' . $identifier;
                        }
                    } catch (\Throwable) {
                        // fall through to current URL
                    }
                }
            }
        } catch (\Throwable) {
            // fall through to current URL
        }

        try {
            $currentUrl = (string) $this->storeManager->getStore()->getCurrentUrl(false);
            return $this->stripQuery($currentUrl);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Strip the query string and fragment from a URL for canonical use.
     *
     * @param string $url
     * @return string
     */
    private function stripQuery(string $url): string
    {
        $q = strpos($url, '?');
        if ($q !== false) {
            $url = substr($url, 0, $q);
        }
        $f = strpos($url, '#');
        if ($f !== false) {
            $url = substr($url, 0, $f);
        }
        return $url;
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
                $mediaUrl = rtrim((string) $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/');
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
            return [self::ENTITY_PRODUCT, (int) $product->getId()];
        }

        $category = $this->registry->registry('current_category');
        if ($category !== null && $category->getId()) {
            return [self::ENTITY_CATEGORY, (int) $category->getId()];
        }

        $cmsPage = $this->registry->registry('cms_page');
        if ($cmsPage !== null && $cmsPage->getId()) {
            return [self::ENTITY_CMS, (int) $cmsPage->getId()];
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
