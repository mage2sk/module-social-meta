<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Model\Social;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class OpenGraphResolver
{
    public const ENTITY_PRODUCT  = 'product';
    public const ENTITY_CATEGORY = 'category';

    public const XML_DEFAULT_OG_IMAGE = 'panth_social_meta/social/default_og_image';

    public function __construct(
        private readonly Registry $registry,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ?PageConfig $pageConfig = null
    ) {
    }

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
        $tags['og:type']        = $this->resolveType($entityType);
        $tags['og:title']       = $this->resolveTitle($entityType);
        $tags['og:description'] = $this->resolveDescription($entityType);
        $tags['og:image']       = $this->resolveImage($entityType);
        $tags['og:url']         = $this->resolveUrl($entityType, $entityId, $storeId);
        $tags['og:site_name']   = $this->resolveSiteName($storeId);
        $tags['og:locale']      = $this->resolveLocale($storeId);

        if ($entityType === self::ENTITY_PRODUCT) {
            foreach ($this->resolveProductTags() as $property => $value) {
                $tags[$property] = $value;
            }
        }

        return array_filter($tags, static fn (string $v): bool => $v !== '');
    }

    private function resolveLocale(int $storeId): string
    {
        try {
            $locale = (string) $this->scopeConfig->getValue(
                'general/locale/code',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            return $locale !== '' ? $locale : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function resolveProductTags(): array
    {
        $product = $this->registry->registry('current_product');
        if (!$product instanceof Product) {
            return [];
        }

        $out = [];

        $finalPrice = $product->getFinalPrice();
        if ($finalPrice === null || $finalPrice === false) {
            try {
                $finalPrice = (float) $product->getPriceInfo()->getPrice('final_price')->getValue();
            } catch (\Throwable) {
                $finalPrice = 0.0;
            }
        }
        $finalPrice = (float) $finalPrice;
        if ($finalPrice > 0.0) {
            try {
                $currency = (string) $this->storeManager->getStore()->getCurrentCurrencyCode();
            } catch (\Throwable) {
                $currency = '';
            }
            if ($currency !== '') {
                $out['product:price:amount']   = number_format($finalPrice, 2, '.', '');
                $out['product:price:currency'] = $currency;
            }
        }

        $isSalable = true;
        if (method_exists($product, 'isSalable')) {
            try {
                $isSalable = (bool) $product->isSalable();
            } catch (\Throwable) {
                $isSalable = true;
            }
        }
        $out['product:availability'] = $isSalable ? 'instock' : 'oos';

        if (method_exists($product, 'getAttributeText')) {
            try {
                $brand = (string) $product->getAttributeText('manufacturer');
            } catch (\Throwable) {
                $brand = '';
            }
            if ($brand !== '') {
                $out['product:brand'] = $brand;
            }
        }

        return $out;
    }

    private function resolveType(?string $entityType): string
    {
        return $entityType === self::ENTITY_PRODUCT ? 'product' : 'website';
    }

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
        }
        return '';
    }

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
        } catch (\Throwable) {
        }

        try {
            $currentUrl = (string) $this->storeManager->getStore()->getCurrentUrl(false);
            return $this->stripQuery($currentUrl);
        } catch (\Throwable) {
            return '';
        }
    }

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

    private function resolveSiteName(int $storeId = 0): string
    {
        try {
            $brand = (string) $this->scopeConfig->getValue(
                'general/store_information/name',
                ScopeInterface::SCOPE_STORE,
                $storeId > 0 ? $storeId : null
            );
            if ($brand !== '') {
                return $brand;
            }
            return (string) $this->storeManager->getStore()->getName();
        } catch (\Throwable) {
            return '';
        }
    }

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
        }

        return '';
    }

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

        return [null, 0];
    }

    private function truncate(string $text, int $maxLength): string
    {
        $text = trim(strip_tags($text));
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $maxLength - 3)) . '...';
    }
}
