<?php
declare(strict_types=1);

namespace Etechflow\CanonicalHreflang\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const P = 'etechflow_canonical/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LicenseValidator $licenseValidator
    ) {
    }

    private function flag(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::P . $path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isEnabled(?int $storeId = null): bool
    {
        // Single storefront chokepoint: the head-links plugin and both resolvers
        // all gate on this, so an invalid / suspended / expired / wrong-domain
        // licence fully blocks canonical + hreflang output. The licence result is
        // cached (~30 s), so it costs at most one portal call per half-minute.
        return $this->flag('general/enabled', $storeId) && $this->licenseValidator->isValid();
    }

    public function isCanonicalEnabled(?int $storeId = null): bool
    {
        return $this->flag('canonical/enabled', $storeId);
    }

    public function canonicalForType(string $type, ?int $storeId = null): bool
    {
        return match ($type) {
            'product'  => $this->flag('canonical/product', $storeId),
            'category' => $this->flag('canonical/category', $storeId),
            'cms_page' => $this->flag('canonical/cms', $storeId),
            default    => false,
        };
    }

    public function stripQuery(?int $storeId = null): bool
    {
        return $this->flag('canonical/strip_query', $storeId);
    }

    public function paginatedToSelf(?int $storeId = null): bool
    {
        return $this->flag('canonical/paginated_to_self', $storeId);
    }

    public function isHreflangEnabled(?int $storeId = null): bool
    {
        return $this->flag('hreflang/enabled', $storeId);
    }

    public function xDefaultStore(?int $storeId = null): ?int
    {
        $v = $this->scopeConfig->getValue(self::P . 'hreflang/x_default_store', ScopeInterface::SCOPE_STORE, $storeId);
        return ($v !== null && $v !== '') ? (int) $v : null;
    }

    /**
     * Optional overrides: lines of "<store_id>:<hreflang_code>". Stores not
     * listed fall back to their general/locale/code (en_GB -> en-gb).
     *
     * @return array<int,string>
     */
    public function hreflangMapping(?int $storeId = null): array
    {
        $raw = (string) $this->scopeConfig->getValue(self::P . 'hreflang/mapping', ScopeInterface::SCOPE_STORE, $storeId);
        if ($raw === '') {
            return [];
        }
        $map = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$sid, $code] = array_map('trim', explode(':', $line, 2));
            if (ctype_digit($sid) && $code !== '') {
                $map[(int) $sid] = $code;
            }
        }
        return $map;
    }
}
