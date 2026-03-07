<?php

declare(strict_types=1);

namespace App\Channels;

use App\Models\PushSubscription;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Custom Laravel notification channel for Web Push (VAPID).
 *
 * Drop-in replacement for laravel-notification-channels/webpush that uses
 * minishlink/web-push directly — fully compatible with Laravel 12.
 *
 * The notification must implement:
 *   public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
 */
class WebPushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush')) {
            return;
        }

        /** @var \App\Models\User $notifiable */
        $subscriptions = PushSubscription::where('user_id', $notifiable->getKey())->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        /** @var WebPushMessage $message */
        $message = $notification->toWebPush($notifiable, $notification);
        $payload = json_encode($message->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('webpush.vapid.subject', env('VAPID_SUBJECT', 'mailto:admin@example.com')),
                'publicKey' => config('webpush.vapid.public_key', env('VAPID_PUBLIC_KEY', '')),
                'privateKey' => config('webpush.vapid.private_key', env('VAPID_PRIVATE_KEY', '')),
            ],
        ]);

        foreach ($subscriptions as $subscription) {
            try {
                $vapidSubscription = Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => 'aesgcm',
                ]);

                $report = $webPush->sendOneNotification($vapidSubscription, $payload);

                // If the subscription is expired/invalid, delete it
                if ($report->isSubscriptionExpired()) {
                    $subscription->delete();
                }
            } catch (\Throwable $e) {
                Log::warning('WebPushChannel: failed to send push notification', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
