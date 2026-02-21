<?php

namespace App\Mail;

use App\Models\Plan;
use Illuminate\Bus\Queueable;
use Illuminate\Collection\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyPlanMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Plan $plan,
        public readonly mixed $tasks,
        public readonly string $date,
    ) {}

    public function envelope(): Envelope
    {
        $title = $this->plan->normalized_json['title'] ?? 'Your Plan';

        return new Envelope(
            subject: "DailyPro: Plan del dia - {$this->date} | {$title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-plan',
        );
    }
}
