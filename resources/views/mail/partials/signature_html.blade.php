@php $__signature = $previewSignatureOverride ?? ($mailSignature ?? ''); @endphp
@if($__signature !== '')
<tr>
    <td style="padding:0 24px 24px;font-size:13px;line-height:1.6;color:#475569;border-top:1px solid #e2e8f0;">
        <div style="padding-top:16px;">{!! nl2br(e($__signature)) !!}</div>
    </td>
</tr>
@endif
