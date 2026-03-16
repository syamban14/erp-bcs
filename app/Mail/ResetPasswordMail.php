<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $newPassword;
    public string $userName;

    public function __construct(string $newPassword, string $userName = '')
    {
        $this->newPassword = $newPassword;
        $this->userName    = $userName;
    }

    public function build(): static
    {
        return $this
            ->subject('🔑 Password Baru - PresensiApp')
            ->view('emails.reset-password');
    }
}
