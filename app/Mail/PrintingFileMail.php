<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class PrintingFileMail extends Mailable
{
    /**
     * @param  array<int, array{name: string, size_bytes: int, url: string}>  $attachmentLinks
     */
    public function __construct(
        public string $mailSubject,
        public string $mailBody,
        public ?string $transferUrl,
        public array $attachmentLinks = [],
    ) {}

    public function build(): self
    {
        return $this->subject($this->mailSubject)
            ->view('emails.printing-file')
            ->with([
                'mailBody' => $this->mailBody,
                'transferUrl' => $this->transferUrl,
                'attachmentLinks' => $this->attachmentLinks,
            ]);
    }
}
