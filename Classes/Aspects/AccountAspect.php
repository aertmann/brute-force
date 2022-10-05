<?php

namespace AE\BruteForce\Aspects;

use GuzzleHttp\Psr7\ServerRequest;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\Http\ServerRequestAttributes;
use Neos\Flow\Security\Account;
use Neos\SwiftMailer\Message;

/**
 * Advice the Account to deactivate if failed attempts threshold is exceeded
 *
 * @Flow\Aspect
 * @Flow\Scope("singleton")
 */
class AccountAspect
{

    /**
     * @Flow\InjectConfiguration(package="AE.BruteForce")
     * @var array
     */
    protected $settings;

    /**
     * @Flow\AfterReturning("method(Neos\Flow\Security\Account->authenticationAttempted())")
     * @param JoinPointInterface $joinPoint
     * @return void
     */
    public function bruteForceAccountLocking(JoinPointInterface $joinPoint)
    {
        $failedAttemptsThreshold = intval($this->settings['failedAttemptsThreshold']);
        if ($failedAttemptsThreshold === 0) {
            return;
        }

        /** @var \Neos\Flow\Security\Account $account */
        $account = $joinPoint->getProxy();

        // Deactivate account if failed attempts exceed threshold
        if ($account->getFailedAuthenticationCount() >= $failedAttemptsThreshold) {
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
        if (!$notificationMailSettings['to']) {
            return;
        }

        $httpRequest = ServerRequest::fromGlobals();

        $failedAttemptsThreshold = $this->settings['failedAttemptsThreshold'];
        $time = (new \DateTime())->format('Y-m-d H:i');

        $replacePlaceholders = function ($string) use ($account, $httpRequest, $failedAttemptsThreshold, $time) {
            return str_replace([
                '{domain}', '{ip}', '{userAgent}', '{accountIdentifier}', '{failedAttemptsThreshold}', '{time}'
            ], [
                $httpRequest->getUri()->getHost(),
                $httpRequest->getAttribute(ServerRequestAttributes::CLIENT_IP),
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
