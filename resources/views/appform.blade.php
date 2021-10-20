<!DOCTYPE html>
<html>
<head>
<!-- <style> * { display: none !important; } </style> -->
<title>AppBeauty Payment</title>
</head>
<body>
  <div class="verystupidimplementation">
<!--     <form action="http://sandboxsecure.mobilpay.ro" method="post"> -->
    @php
      $html_decoded = json_decode($html);
    @endphp
    @if($html_decoded)
    <form id="form-mobilpay" action="{{$html_decoded->postUrl}}" method="POST" name="frmPaymentRedirect">
      <input name="env_key" type="hidden" value="{{$html_decoded->env_key}}"/>
      <input name="data" type="hidden"  value="{{$html_decoded->data}}"/>
    </form>
    @endif
    
  </div>
</body>
<script>
     document.addEventListener("DOMContentLoaded", function () {
        setTimeout(function () {
          document.getElementById('form-mobilpay').submit();
        }, 1000);
	   });
</script>

</html>

