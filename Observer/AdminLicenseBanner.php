<?php
/**
 * Persistent admin banner shown when the module is LICENSED (a key is set) but
 * the licence is NOT currently valid — suspended, blocked, expired or wrong
 * domain. The licence key is never removed: the module simply freezes (no
 * canonical / hreflang output via Config::isEnabled()) and the merchant is told
 * why and how to restore access. Active licences (and the unlicensed/no-key
 * state, which has its own purchase gate) show no banner.
 */
declare(strict_types=1);

namespace Etechflow\CanonicalHreflang\Observer;

use Etechflow\CanonicalHreflang\Model\LicenseValidator;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;

class AdminLicenseBanner implements ObserverInterface
{
    public function __construct(
        private readonly LicenseValidator $licenseValidator,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(Observer $observer): void
    {
        // Only when a key IS configured and it is NOT currently valid.
        if ($this->licenseValidator->getConfiguredKey() === '' || $this->licenseValidator->isValid()) {
            return;
        }

        $state = $this->licenseValidator->getLicenseState();

        if ($state === 'suspended' || $state === 'blocked') {
            $this->messageManager->addWarningMessage(__(
                'Etechflow Canonical & Hreflang — licence %1. The module is disabled (no canonical or hreflang tags '
                . 'are output) until access is restored. Your licence key has been kept. Please contact '
                . 'support@etechflow.com to restore access.',
                $state === 'suspended' ? __('suspended') : __('blocked')
            ));
        } else {
            $this->messageManager->addWarningMessage(__(
                'Etechflow Canonical & Hreflang — licence is not active (expired or invalid for this domain). '
                . 'The module is disabled. Re-activate it under Stores > Configuration > ETECHFLOW > '
                . 'Canonical & Hreflang > License.'
            ));
        }
    }
}
