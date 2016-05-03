<?php
namespace AE\BruteForce\Aspects;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Aop\JoinPointInterface;
use TYPO3\Flow\Http\Request;
use TYPO3\Flow\Security\Account;
use TYPO3\SwiftMailer\Message;

/**
 * Advice the RuntimeContentCache to check for uncached segments that should prevent caching
 *
 * @Flow\Aspect
 * @Flow\Scope("singleton")
 */
class AccountAspect {

    /**
     * @var array
     */
    protected $settings;

    /**
     * @param array $settings
     * @return void
     */
    public function injectSettings(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @Flow\AfterReturning("method(TYPO3\Flow\Security\Account->authenticationAttempted())")
     * @param JoinPointInterface $joinPoint
     */
    public function bruteForceAccountLocking(JoinPointInterface $joinPoint)
    {
        /** @var \TYPO3\Flow\Security\Account $account */
        $account = $joinPoint->getProxy();

        // Deactivate account if failed attempts exceed threshold
        if ($account->getFailedAuthenticationCount() >= intval($this->settings['failedAttemptsThreshold'])) {
            $account->setExpirationDate(new \DateTime());
            $this->sendNotificationMail($account);
        }
    }

    /**
     * @param Account $account
     * @return void
     */
    protected function sendNotificationMail(Account $account)
    {
        $notificationMailSettings = $this->settings['notificationMail'];
        if (!$notificationMailSettings['to']['email']) {
            return;
        }
        $httpRequest = Request::createFromEnvironment();
        $failedAttemptsThreshold = $this->settings['failedAttemptsThreshold'];
        $replacePlaceholders = function ($string) use ($account, $httpRequest, $failedAttemptsThreshold) {
            return str_replace([
                '{domain}', '{ip}', '{accountIdentifier}', '{failedAttemptsThreshold}'
            ], [
                $httpRequest->getUri()->getHost(),
                $httpRequest->getClientIpAddress(),
                $account->getAccountIdentifier(),
                $failedAttemptsThreshold
            ], $string);
        };

        $mail = new Message();
        $mail
            ->setFrom(
                $notificationMailSettings['from']['email'],
                $replacePlaceholders($notificationMailSettings['from']['name'])
            )
            ->setTo(
                $notificationMailSettings['to']['email'],
                $replacePlaceholders($notificationMailSettings['to']['name'])
            )
            ->setSubject($replacePlaceholders($notificationMailSettings['subject']))
            ->setBody($replacePlaceholders($notificationMailSettings['message']))
            ->send();
    }

}