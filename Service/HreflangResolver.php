<?php
declare(strict_types=1);

namespace Etechflow\CanonicalHreflang\Service;

use Etechflow\CanonicalHreflang\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

/**
 * Builds rel="alternate" hreflang links for the current entity across every
 * active store view. Each store's hreflang code comes from an admin override
 * ("<store_id>:<code>") or falls back to its general/locale/code (en_GB -> en-gb).
 */
class HreflangResolver
{
    public function __construct(
        private readonly Registry $registry,
        private readonly Http $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlFinderInterface $urlFinder,
        private readonly Config $config
    ) {
    }

    /**
     * @return array<int,array{hreflang:string,href:string}>
     */
    public function resolve(): array
    {
        if (!$this->config->isEnabled() || !$this->config->isHreflangEnabled()) {
            return [];
        }
        $type = $this->pageType();
        if (!$type) {
            return [];
        }
        [$entityType, $entityId, $cmsIdentifier] = $this->entityRef($type);
        if ($entityId === null && $cmsIdentifier === null) {
            return [];
        }

        $mapping = $this->config->hreflangMapping();
        $xDefaultStore = $this->config->xDefaultStore();
        $out = [];
        $xDefaultHref = null;

        foreach ($this->storeManager->getStores() as $store) {
            if (!$store->getIsActive()) {
                continue;
            }
            $sid = (int) $store->getId();
            $href = $this->urlInStore($store, $entityType, $entityId, $cmsIdentifier);
            if (!$href) {
                continue;
            }
            $code = $mapping[$sid] ?? $this->localeToHreflang($sid);
            if ($code === '') {
                continue;
            }
            $out[] = ['hreflang' => $code, 'href' => $href];
            if ($xDefaultStore !== null && $sid === $xDefaultStore) {
                $xDefaultHref = $href;
            }
        }

        if ($xDefaultHref !== null) {
            $out[] = ['hreflang' => 'x-default', 'href' => $xDefaultHref];
        }

        // Only meaningful with 2+ alternates (or an x-default).
        return count($out) >= 2 ? $out : [];
    }

    /** @return array{0:?string,1:?int,2:?string} [entityType, entityId, cmsIdentifier] */
    private function entityRef(string $type): array
    {
        if ($type === 'product') {
            $p = $this->registry->registry('current_product');
            return ['product', ($p && $p->getId()) ? (int) $p->getId() : null, null];
        }
        if ($type === 'category') {
            $c = $this->registry->registry('current_category');
            return ['category', ($c && $c->getId()) ? (int) $c->getId() : null, null];
        }
        $page = $this->registry->registry('cms_page');
        $ident = ($page && method_exists($page, 'getIdentifier')) ? (string) $page->getIdentifier() : null;
        return ['cms_page', null, $ident];
    }

    private function urlInStore($store, ?string $entityType, ?int $entityId, ?string $cmsIdentifier): ?string
    {
        $base = $store->getBaseUrl(UrlInterface::URL_TYPE_LINK);
        if ($entityType === 'cms_page') {
            if ($cmsIdentifier === null || $cmsIdentifier === '') {
                return null;
            }
            return $cmsIdentifier === 'home' ? $base : rtrim($base, '/') . '/' . $cmsIdentifier;
        }
        if ($entityId === null) {
            return null;
        }
        $rewrite = $this->urlFinder->findOneByData([
            UrlRewrite::ENTITY_TYPE => $entityType,
            UrlRewrite::ENTITY_ID   => $entityId,
            UrlRewrite::STORE_ID    => (int) $store->getId(),
            UrlRewrite::REDIRECT_TYPE => 0,
        ]);
        if (!$rewrite) {
            return null;
        }
        return rtrim($base, '/') . '/' . ltrim($rewrite->getRequestPath(), '/');
    }

    private function localeToHreflang(int $storeId): string
    {
        $locale = (string) $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $storeId);
        return $locale !== '' ? strtolower(str_replace('_', '-', $locale)) : '';
    }

    private function pageType(): ?string
    {
        return match ($this->request->getFullActionName()) {
            'catalog_product_view'             => 'product',
            'catalog_category_view'            => 'category',
            'cms_page_view', 'cms_index_index' => 'cms_page',
            default                            => null,
        };
    }
}
