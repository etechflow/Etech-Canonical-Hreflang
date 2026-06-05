<?php
declare(strict_types=1);

namespace Etechflow\CanonicalHreflang\Plugin\Frontend;

use Etechflow\CanonicalHreflang\Model\Config;
use Etechflow\CanonicalHreflang\Service\CanonicalResolver;
use Etechflow\CanonicalHreflang\Service\HreflangResolver;
use Magento\Framework\App\Request\Http;
use Magento\Framework\View\Result\Page as ResultPage;

/**
 * Injects rel="canonical" and rel="alternate" hreflang links into the page head
 * via PageConfig remote assets (renders in any theme, incl. Hyvä). Removes any
 * existing canonical asset first so we never emit two canonical tags when core's
 * canonical is also enabled.
 */
class HeadLinksPlugin
{
    public function __construct(
        private readonly CanonicalResolver $canonicalResolver,
        private readonly HreflangResolver $hreflangResolver,
        private readonly Config $config,
        private readonly Http $request
    ) {
    }

    public function beforeRenderResult(ResultPage $subject, $response)
    {
        try {
            if (!$this->config->isEnabled() || $this->request->isAjax()) {
                return null;
            }
            $config = $subject->getConfig();

            $canonical = $this->canonicalResolver->resolve();
            if ($canonical) {
                $this->removeExistingCanonical($config);
                $config->addRemotePageAsset(
                    $canonical,
                    'canonical',
                    ['attributes' => ['rel' => 'canonical']]
                );
            }

            foreach ($this->hreflangResolver->resolve() as $alt) {
                $config->addRemotePageAsset(
                    $alt['href'],
                    'hreflang-' . $alt['hreflang'],
                    ['attributes' => ['rel' => 'alternate', 'hreflang' => $alt['hreflang']]]
                );
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    private function removeExistingCanonical($config): void
    {
        try {
            $assets = $config->getAssetCollection();
            foreach ($assets->getAll() as $identifier => $asset) {
                if (method_exists($asset, 'getContentType') && $asset->getContentType() === 'canonical') {
                    $assets->remove($identifier);
                }
            }
        } catch (\Throwable $e) {
            // best effort — if we can't dedupe, still add ours
        }
    }
}
