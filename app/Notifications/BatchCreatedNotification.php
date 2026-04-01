<?php

namespace App\Notifications;

use App\Models\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BatchCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Batch $batch)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Batch #{$this->batch->id} Created Successfully")
            ->line("A new batch has been created for you.")
            ->line("**Batch Details:**")
            ->line("- Insurer: {$this->batch->insurer->name}")
            ->line("- Provider: {$this->batch->provider_name}")
            ->line("- Batch Date: {$this->batch->batch_date->format('Y-m-d')}")
            ->line("- Total Claims: {$this->batch->total_claims}")
            ->line("- Total Processing Cost: ₦{$this->batch->total_cost}")
            ->action("View Batch", url("/batches/{$this->batch->id}"))
            ->line("Thank you for using our system!");
    }
}
