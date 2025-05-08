<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

class ClassTimetableUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;

    /**
     * Create a new notification instance.
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];
        
        // Check user preferences if available
        if ($notifiable->notificationPreference) {
            $prefs = $notifiable->notificationPreference;
            
            if (!$prefs->update_notifications_enabled) {
                return [];
            }
            
            if ($prefs->sms_enabled && $notifiable->phone) {
                $channels[] = 'vonage';
            }
        }
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $classDetails = $this->data['class_details'] ?? [];
        if (isset($classDetails['venue'])) {
            // Clean up venue text to avoid duplication of "(Updated)"
            $classDetails['venue'] = preg_replace('/\s*\(Updated\)\s*/', '', $classDetails['venue']);
            $classDetails['venue'] = trim($classDetails['venue']) . ' (Updated)';
        }

        return (new MailMessage)
            ->subject($this->data['subject'] ?? 'Class Schedule Update')
            ->greeting($this->data['greeting'] ?? 'Hello ' . $notifiable->first_name)
            ->line($this->data['message'] ?? 'There has been an update to your class schedule.')
            ->line('Class Details:')
            ->line('Unit: ' . ($classDetails['unit'] ?? 'N/A'))
            ->line('Day: ' . ($classDetails['day'] ?? 'N/A'))
            ->line('Time: ' . ($classDetails['time'] ?? 'N/A'))
            ->line('Venue: ' . ($classDetails['venue'] ?? 'N/A'))
            ->line('Changes Made:')
            ->when(!empty($this->data['changes']), function ($mailMessage) {
                foreach ($this->data['changes'] as $field => $values) {
                    $fieldName = ucfirst(str_replace('_', ' ', $field));
                    $mailMessage->line("{$fieldName}: Changed from \"{$values['old']}\" to \"{$values['new']}\"");
                }
            })
            ->line($this->data['closing'] ?? 'Please review these changes and plan accordingly.')
            // ->action('View Updated Timetable', route('student.timetable'))
            // ->line('Regards,')
            ->line('Timetabling System Management Office');
    }
    
    /**
     * Get the Vonage / SMS representation of the notification.
     */
    public function toVonage(object $notifiable): VonageMessage
    {
        $classInfo = $this->data['class_details'];
        $changes = $this->data['changes'];
        
        $changeText = '';
        if (!empty($changes)) {
            $changeText = " Changes: ";
            foreach ($changes as $field => $values) {
                $changeText .= "{$field} from {$values['old']} to {$values['new']}; ";
            }
        }
        
        $content = "CLASS UPDATE: {$classInfo['unit']}{$changeText}Check your email for details.";
        
        return (new VonageMessage)
            ->content($content)
            ->unicode();
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
            'greeting' => $this->data['greeting'],
            'message' => $this->data['message'],
            'class_details' => $this->data['class_details'],
            'changes' => $this->data['changes'],
            'closing' => $this->data['closing']
        ];
    }
}