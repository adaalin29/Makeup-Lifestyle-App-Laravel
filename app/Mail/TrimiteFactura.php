<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;

class TrimiteFactura extends Mailable
{
    use Queueable, SerializesModels;
  
    public $message = false;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($message)
    {
        //
      $this->message = $message;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $invoice = json_decode($this->message->invoice, true)[0];
        $path = storage_path('app/'.$invoice['download_link']);
        return $this->markdown('emails.factura')
            ->subject('Factura Makeup Lifestyle App')
            ->attach($path, [
                'as' => $invoice['original_name'],
                'mime' => 'application/pdf',
            ]);
    }
}
