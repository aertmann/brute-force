<?php
namespace AE\BruteForce\Aspects;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Aop\JoinPointInterface;
use TYPO3\Flow\Http\Request;
use TYPO3\Flow\Security\Account;
use TYPO3\SwiftMailer\Message;

/**
 * Advice the Account to deactivate if failed attempts threshold is exceeded
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
     * Do the check if an account should be blocked because of brute force attempt
     *
     * @Flow\AfterReturning("method(TYPO3\Flow\Security\Account->authenticationAttempted())")
     * @param JoinPointInterface $joinPoint
     * @return void
     */
    public function bruteForceAccountLocking(JoinPointInterface $joinPoint)
    {
        $failedAttemptsThreshold = intval($this->settings['failedAttemptsThreshold']);
        if ($failedAttemptsThreshold === 0) {
            return;
        }

        /** @var \TYPO3\Flow\Security\Account $account */
        $account = $joinPoint->getProxy();

        // Deactivate account if failed attempts exceed threshold
        if ($account->getFailedAuthenticationCount() >= $failedAttemptsThreshold) {
            $account->setExpirationDate(new \DateTime());
            $this->sendNotificationMail($account);
        }
    }

    /**
     * Send the notification mail
     *
     * @param Account $account
     * @return void
     */
    protected function sendNotificationMail(Account $account)
    {
        $notificationMailSettings = $this->settings['notificationMail'];
        if (!$notificationMailSettings['to']) {
            return;
        }
        $httpRequest = Request::createFromEnvironment();
        $failedAttemptsThreshold = $this->settings['failedAttemptsThreshold'];
        $time = (new \DateTime())->format('Y-m-d H:i');

        $replacePlaceholders = function($string) use ($account, $httpRequest, $failedAttemptsThreshold, $time) {
            return str_replace([
                '{domain}', '{ip}', '{userAgent}', '{accountIdentifier}', '{failedAttemptsThreshold}', '{time}'
            ], [
                $httpRequest->getUri()->getHost(),
                $httpRequest->getClientIpAddress(),
                $_SERVER['HTTP_USER_AGENT'],
                $account->getAccountIdentifier(),
                $failedAttemptsThreshold,
                $time
            ], $string);
        };

        $mail = new Message();
        $mail
            ->setFrom(
                $replacePlaceholders($notificationMailSettings['from']['email']),
                $replacePlaceholders($notificationMailSettings['from']['name'])
            )
            ->setTo($notificationMailSettings['to'])
            ->setSubject($replacePlaceholders($notificationMailSettings['subject']))
            ->setBody($replacePlaceholders($notificationMailSettings['message']))
            ->send();
    }

}
