{{-- JS twin of App\Support\SmsSegments::analyse() for live counters. Keep the two in step. --}}
<script>
    window.nrcsSmsSegments = window.nrcsSmsSegments || (function () {
        const basic = new Set(Array.from("@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà"));
        const extension = new Set(Array.from("^{}\\[~]|€\f"));

        return function (text) {
            const chars = Array.from(text || '');
            let gsmUnits = 0;
            const nonGsm = new Set();
            for (const c of chars) {
                if (basic.has(c)) gsmUnits += 1;
                else if (extension.has(c)) gsmUnits += 2;
                else nonGsm.add(c);
            }
            const unicode = nonGsm.size > 0;
            const units = unicode ? (text || '').length : gsmUnits; // JS length = UTF-16 units
            const single = unicode ? 70 : 160;
            const multi = unicode ? 67 : 153;
            const segments = units === 0 ? 0 : (units <= single ? 1 : Math.ceil(units / multi));
            return {
                encoding: unicode ? 'UCS-2' : 'GSM-7',
                chars: chars.length,
                units: units,
                segments: segments,
                nonGsm: Array.from(nonGsm),
            };
        };
    })();
</script>
