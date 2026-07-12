<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PeriodicReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $reportType,
        public string $reportPeriodLabel,
        public string $pdfPath,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->reportType === 'monthly'
                ? 'Relatório mensal em PDF'
                : 'Relatório anual em PDF',
            from: new Address(config('mail.from.address'), config('mail.from.name')),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.periodic-report',
            with: [
                'user' => $this->user,
                'reportType' => $this->reportType,
                'reportPeriodLabel' => $this->reportPeriodLabel,
                'unsubscribeUrl' => rtrim(config('app.url'), '/') . '/minha-conta',
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->pdfPath)
                ->as($this->reportType === 'monthly' ? 'relatorio-mensal.pdf' : 'relatorio-anual.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
