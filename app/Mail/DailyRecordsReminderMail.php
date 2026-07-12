<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyRecordsReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $conditionLabel,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Lembrete de registros financeiros',
            from: new Address(config('mail.from.address'), config('mail.from.name')),
        );
    }

    public function content(): Content
    {
        $baseUrl = rtrim(config('app.url'), '/');

        return new Content(
            view: 'emails.daily-records-reminder',
            with: [
                'user' => $this->user,
                'conditionLabel' => $this->conditionLabel,
                'platformUrl' => $baseUrl . '/dashboard',
                'unsubscribeUrl' => $baseUrl . '/minha-conta',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
