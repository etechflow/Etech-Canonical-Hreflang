<?php
declare(strict_types=1);

namespace Etechflow\CanonicalHreflang\Service;

use Etechflow\CanonicalHreflang\Model\Config;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Computes the canonical URL for the current page. Strips query/tracking params
 * (configurable) so filtered, sorted and ?-variant URLs all point at one clean
 * URL; products canonicalise to their category-free URL so a product reachable
 * under several category paths has a single canonical.
 */
class CanonicalResolver
{
    public function __construct(
        private readonly Registry $registry,
        private readonly Http $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {
    }

    public function resolve(): ?string
    {
        if (!$this->config->isEnabled() || !$this->config->isCanonicalEnabled()) {
            return null;
        }
        $type = $this->pageType();
        if (!$type || !$this->config->canonicalForType($type)) {
            return null;
        }

        try {
            $url = $this->baseUrlForType($type);
        } catch (\Throwable $e) {
            return null;
        }
        if (!$url) {
            return null;
        }

        if ($this->config->stripQuery()) {
            $url = strtok($url, '?') ?: $url;
        }

        // Self-referencing pagination: keep ?p=N on category pages when enabled.
        if ($type === 'category' && $this->config->paginatedToSelf()) {
            $p = (int) $this->request->getParam('p');
            if ($p > 1) {
                $url .= (str_contains($url, '?') ? '&' : '?') . 'p=' . $p;
            }
        }

        return $url;
    }

    private function baseUrlForType(string $type): ?string
    {
        switch ($type) {
            case 'product':
                $p = $this->registry->registry('current_product');
                if (!$p || !$p->getId()) {
                    return null;
                }
                return $p->getUrlModel()->getUrl($p, ['_ignore_category' => true, '_nosid' => true]);
            case 'category':
                $c = $this->registry->registry('current_category');
                if (!$c || !$c->getId()) {
                    return null;
                }
                return $c->getUrl();
            case 'cms_page':
                $store = $this->storeManager->getStore();
                $base = $store->getBaseUrl(UrlInterface::URL_TYPE_LINK);
                if ($this->request->getFullActionName() === 'cms_index_index') {
                    return $base;
                }
                $page = $this->registry->registry('cms_page');
                $ident = $page instanceof CmsPage ? (string) $page->getIdentifier() : '';
                return $ident !== '' ? rtrim($base, '/') . '/' . $ident : $base;
        }
        return null;
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
