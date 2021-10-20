@component('mail::message')
# Mesaj nou

@component('mail::panel')
<div style="width:100%; text-align:center; font-size:30px; font-height:bold;">
  Recuperare parola {{ config('app.name') }}
</div>
In urma solicitarii dvs., am trimis un cod de verificare pentru recuperarea parolei.<br>
Introduceti codul <strong>{{$cod}}</strong> in formularul de pe site, dupa care introduceti noua parola.<br>
Daca nu ati facut nicio actiune pe aplicatia {{ config('app.name') }} , va rugam sa ignorati acest email!<br>

@endcomponent

Va multumim,<br>
Echipa {{ config('app.name') }}
@endcomponent
