<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMessageCod extends Mailable
{
    use Queueable, SerializesModels;
  
    public $cod = false;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($cod)
    {
        //
      $this->cod = $cod;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->withSwiftMessage(function ($message) {
        $message->getHeaders()
                ->addTextHeader('Custom-Header', 'HeaderValue');
        });
        return $this->markdown('emails.cod')->subject('Modificare parola Beauty App');;
    }
}
