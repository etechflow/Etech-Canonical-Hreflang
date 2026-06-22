<?php

declare(strict_types=1);

namespace Etechflow\CanonicalHreflang\Controller\Adminhtml\License;

use Etechflow\CanonicalHreflang\Model\LicenseValidator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * License-required gate page. Shows plan cards + "Enter License Key".
 * Redirects to the module configuration when the license is already valid
 * (this module has no admin dashboard — its output is storefront-only).
 */
class Gate extends Action
{
    public const ADMIN_RESOURCE = 'Etechflow_CanonicalHreflang::config';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly LicenseValidator $licenseValidator
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        if ($this->licenseValidator->isValid()) {
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)
                ->setPath('adminhtml/system_config/edit/section/etechflow_canonical');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->prepend(__('Canonical & Hreflang — License Required'));
        $portalBase = rtrim(str_replace('/license/validate', '', $this->licenseValidator->getPortalUrl()), '/');
        $domain     = $this->licenseValidator->getCurrentHost();
        $plansUrl   = $portalBase . '/license/plans?module=canonical-hreflang&domain=' . urlencode($domain);
        $block = $page->getLayout()->getBlock('canonicalhreflang.license.gate');
        if ($block) {
            $block->setData('plans_url', $plansUrl);
        }
        return $page;
    }
}
