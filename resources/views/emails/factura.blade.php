@component('mail::message')
# Factura Makeup Lifestyle App

@component('mail::panel')
<div style="width:100%; text-align:center; font-size:30px; font-height:bold;">
Aveti un mesaj nou:
</div>

Buna {{$message['name']}},<br>
Achizitia a avut loc cu succes.
Atasat gasesti factura.


@endcomponent

Multumim,<br>
Makeup Lifestyle App.
@endcomponent
