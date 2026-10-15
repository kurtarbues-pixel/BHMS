<?php

namespace App\Mail;

use App\Models\Inquiry;
use App\Models\Lease;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InquiryApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Inquiry $inquiry, public Lease $lease) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your boarding house inquiry was approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.inquiry-approved',
        );
    }
}
