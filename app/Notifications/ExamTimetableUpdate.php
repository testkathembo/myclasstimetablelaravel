<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class ExamTimetableUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;

    /**
     * Create a new notification instance.
     */
    public function __construct($data)
    {
        // Clean up venue text to remove duplicate (Updated) text
        if (isset($data['exam_details']['venue'])) {
            // Remove all instances of "(Updated)" from the venue
            $venue = preg_replace('/\s*$$Updated$$\s*/', ' ', $data['exam_details']['venue']);
            // Trim extra spaces
            $venue = trim($venue);
            $data['exam_details']['venue'] = $venue;
        }
        
        // Clean up changes to show cleaner text
        if (isset($data['changes']['venue'])) {
            $data['changes']['venue']['old'] = preg_replace('/\s*$$Updated$$\s*/', ' ', $data['changes']['venue']['old']);
            $data['changes']['venue']['new'] = preg_replace('/\s*$$Updated$$\s*/', ' ', $data['changes']['venue']['new']);
            $data['changes']['venue']['old'] = trim($data['changes']['venue']['old']);
            $data['changes']['venue']['new'] = trim($data['changes']['venue']['new']);
        }
        
        $this->data = $data;
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
        // Build the email message
        $mailMessage = (new MailMessage)
            ->subject($this->data['subject'])
            ->greeting($this->data['greeting'] . ' ' . $notifiable->first_name)
            ->line($this->data['message']);

        // Add exam details section
        $mailMessage->line('Exam Details:')
            ->line('Unit: ' . $this->data['exam_details']['unit'])
            ->line('Date: ' . $this->data['exam_details']['date'] . ' (' . $this->data['exam_details']['day'] . ')')
            ->line('Time: ' . $this->data['exam_details']['time'])
            ->line('Venue: ' . $this->data['exam_details']['venue']);

        // Add changes section
        if (!empty($this->data['changes'])) {
            $mailMessage->line('Changes Made:');
            
            foreach ($this->data['changes'] as $field => $values) {
                // Format the field name for display
                $fieldName = ucfirst(str_replace('_', ' ', $field));
                $mailMessage->line("{$fieldName}: Changed from \"{$values['old']}\" to \"{$values['new']}\"");
            }
        }

        // Add closing message
        $mailMessage->line($this->data['closing']);

        // Only add action button if the route exists
        if (Route::has('student.timetable')) {
            $mailMessage->action('View Timetable', route('student.timetable'));
        } else {
            // Use a fallback URL or just skip the action button
            $mailMessage->line('Please check your student portal for the latest timetable information.');
        }

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
            'subject' => $this->data['subject'],
            'message' => $this->data['message'],
            'exam_details' => $this->data['exam_details'],
            'changes' => $this->data['changes'],
        ];
    }
}