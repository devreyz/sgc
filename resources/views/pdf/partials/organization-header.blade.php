<table role="presentation" class="sgc-organization-header" style="width:100%;table-layout:fixed;border-collapse:collapse;border-bottom:2px solid {{ $headerColor }};margin:0 0 12px;">
    <tr>
        <td width="66%" style="vertical-align:top;text-align:left;padding:0 0 8px;">
            @php
                $showLogo = filled($brandLogoHtml ?? $brandLogo ?? null);
            @endphp
            <div style="position:relative;{{ $showLogo ? 'padding-left:58px;min-height:50px;' : '' }}word-wrap:break-word;">
                @if($showLogo)
                    <div style="position:absolute;top:0;left:0;width:48px;">
                        @if(filled($brandLogoHtml ?? null))
                            {!! $brandLogoHtml !!}
                        @else
                            <img src="{{ $brandLogo }}" alt="" style="max-width:48px;max-height:50px;">
                        @endif
                    </div>
                @endif
                <div style="font-size:13px;font-weight:bold;line-height:1.25;color:{{ $headerColor }};">{{ $brandName }}</div>
                @if(filled($brandDocument ?? null))
                    <div style="font-size:8px;color:#536158;margin-top:3px;">CNPJ {{ $brandDocument }}</div>
                @endif
                @if(filled($brandContact ?? null))
                    <div style="font-size:8px;color:#536158;margin-top:3px;">{{ $brandContact }}</div>
                @endif
            </div>
        </td>
        <td width="34%" style="vertical-align:top;text-align:right;padding:0 0 8px 16px;word-wrap:break-word;">
            <div style="font-size:12px;font-weight:bold;line-height:1.25;color:{{ $headerColor }};">{{ $headerTitle }}</div>
            @if(filled($headerSubtitle ?? null))
                <div style="font-size:9px;margin-top:4px;color:#536158;">{{ $headerSubtitle }}</div>
            @endif
            @if(filled($headerDate ?? null))
                <div style="font-size:8px;margin-top:3px;color:#536158;">Gerado em {{ $headerDate }}</div>
            @endif
        </td>
    </tr>
</table>
