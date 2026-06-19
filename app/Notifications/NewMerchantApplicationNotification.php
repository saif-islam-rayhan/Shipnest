<?php

namespace App\Notifications;

use App\Models\Merchant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMerchantApplicationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Merchant $merchant,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $user = $this->merchant->user;

        return (new MailMessage)
            ->subject('New Merchant Application - '.$this->merchant->shop_name)
            ->greeting('Hello Admin,')
            ->line('A new merchant has applied to join ShipNest.')
            ->line('**Shop:** '.$this->merchant->shop_name)
            ->line('**Owner:** '.$user->name.' ('.$user->email.')')
            ->line('**Phone:** '.$this->merchant->phone)
            ->line('**District:** '.$this->merchant->district)
            ->action('Review Application', route('admin.shops.index', ['status' => 'pending']))
            ->line('Please review and approve or reject this application.');
    }
}
