@component('mail::message')
# Mesaj nou

@component('mail::panel')
<div style="width:100%; text-align:center; font-size:30px; font-height:bold;">
Aveti un mesaj nou:
</div>

Name: {{$message['name']}}<br>
Email: {{$message['email']}}<br>
Telefon: {{$message['phone']}}<br>
Domeniu de activitate: {{$message['domain']}}<br>


@endcomponent

Multumim,<br>
Makeup lifestyle App.
@endcomponent
