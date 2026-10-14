@php $__signature = $previewSignatureOverride ?? ($mailSignature ?? ''); @endphp
@if($__signature !== '')

--
{{ $__signature }}
@endif
