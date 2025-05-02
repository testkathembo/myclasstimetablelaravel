<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class Exam_reminder extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public $mailData)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject($this->mailData['Humble reminder'])
            ->greeting($this->mailData['Hello'])                   
            ->line($this->mailData['Wish']);
            
        // Add exam details if available
        if (isset($this->mailData['ExamDetails'])) {
            $examDetails = $this->mailData['ExamDetails'];
            
            // Create a more structured and visually appealing exam details section
            $mailMessage->line('')
                ->line('**Exam Details:**')
                ->line('**Unit:** ' . $examDetails['unit'])
                ->line('**Date:** ' . $examDetails['date'] . ' (' . $examDetails['day'] . ')')
                ->line('**Time:** ' . $examDetails['time'])
                ->line('**Venue:** ' . $examDetails['venue'] . ' - ' . $examDetails['location'])
                ->line('');
        }
            
        $mailMessage->line('We wish all the best in your preparation!');
        
        return $mailMessage;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}