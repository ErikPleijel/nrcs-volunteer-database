@php
    $coords = $layout['coords'] ?? [];
@endphp


    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Certificates (Plain)</title>
    @php
        $coords = $layout['coords'] ?? [];
    @endphp
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Roboto:wght@400;500&display=swap');

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
        }

        .certificate-page {
            width: 297mm;   /* A4 landscape */
            height: 210mm;
            margin: 10mm auto;
            position: relative;
            background-color: white;
            page-break-after: always;
        }

        .certificate-info {
            font-size: 10px;
            color: #777;
            text-align: left;
            width: 32mm;
            position: relative;
            left: -20px;
            white-space: normal; /* Allow text to wrap */
        }

        .certificate-page:last-child {
            page-break-after: auto;
        }

        .text-block {
            position: absolute;
            white-space: nowrap;
            transform: translateX(-50%);
            text-align: center;
        }

        .org-name {
            font-size: 18px;
            font-weight: 500;
        }

        .certify-text {
            font-size: 16px;
        }

        .recipient-name {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            font-weight: 700;
        }

        .course-title-text {
            font-size: 16px;
        }

        .course-title {
            font-size: 22px;
            font-weight: 500;
        }

        .training-details {
            font-size: 14px;
        }

        .signature-title {
            font-size: 12px;
            font-weight: 500;
            transform: none; /* signatures use left as left edge */
            text-align: center;
        }

        .footer-info {
            font-size: 10px;
            color: #555;
        }

        .qr-placeholder {
            transform: none; /* Override .text-block transform */
            width: 25mm;
            height: 25mm;
            border: 1px solid #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            color: #555;
            box-sizing: border-box;
        }

        /* --- Layout editor panel --- */

        #toggle-layout-editor {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 9999;
            padding: 6px 10px;
            font-size: 12px;
            background: #444;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            opacity: 0.7;
        }

        #toggle-layout-editor:hover {
            opacity: 1;
        }

        #layout-editor-panel {
            position: fixed;
            top: 40px;
            right: 10px;
            width: 320px;
            max-height: 80vh;
            overflow-y: auto;
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 10px;
            font-size: 12px;
            z-index: 9998;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        #layout-editor-panel.hidden {
            display: none;
        }

        #layout-editor-panel h2 {
            font-size: 14px;
            margin: 0 0 6px 0;
        }

        .editor-row {
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 1px dashed #ddd;
        }

        .editor-label {
            font-weight: 600;
            margin-bottom: 3px;
        }

        .editor-controls {
            display: flex;
            flex-direction: column;
            gap: 2px;
            align-items: flex-start;
        }

        .editor-line {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .editor-line span.axis-label {
            min-width: 28px;
            font-weight: 500;
        }

        .editor-btn {
            padding: 1px 6px;
            font-size: 11px;
            border: 1px solid #aaa;
            border-radius: 3px;
            background: #f5f5f5;
            cursor: pointer;
        }

        .editor-btn:hover {
            background: #e5e5e5;
        }

        #layout-editor-actions {
            margin-top: 8px;
            display: flex;
            justify-content: space-between;
            gap: 6px;
        }

        #layout-editor-actions button {
            flex: 1;
            padding: 4px 6px;
            font-size: 11px;
            border-radius: 4px;
            border: 1px solid #aaa;
            cursor: pointer;
        }

        #save-layout-button {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }

        #save-layout-button:hover {
            background: #1d4ed8;
        }

        #reset-layout-button:hover {
            background: #f3f3f3;
        }

        #info-layout-button {
            background: #f97316;
            color: #fff;
            border-color: #f97316;
        }

        #info-layout-button:hover {
            background: #ea580c;
        }

        @media print {
            body {
                background-color: white;
            }

            .certificate-page {
                margin: 0;
                width: 297mm;
                height: 210mm;
                page-break-after: always;
            }

            /* Hide editor & toggle in print */
            #toggle-layout-editor,
            #layout-editor-panel {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<button id="toggle-layout-editor" type="button">
    Layout editor
</button>

<div id="layout-editor-panel" class="hidden">
    <h2>Layout editor (plain certificates)</h2>
    <p style="font-size: 11px; margin-bottom: 8px;">
        Adjust X/Y (in mm) and font size. Changes apply live to all pages.
    </p>

    <div id="layout-editor-rows">
        {{-- Rows will be generated by JS based on known keys --}}
    </div>

    <div id="layout-editor-actions">
        <button id="reset-layout-button" type="button">Reset to defaults</button>
        <button id="info-layout-button" type="button">Print help</button>
        <button id="save-layout-button" type="button">Save to browser</button>
    </div>
</div>

@if(isset($certificates) && count($certificates) > 0)
    @foreach($certificates as $certificate)
        @php
            $user     = $certificate['user']     ?? null;
            $training = $certificate['training'] ?? null;

            // Default type
            $certificateType = $certificate['certificate_type'] ?? 'training_competence';

            $verificationUrl = null;
            $qrBase64        = null;

            if ($user && !empty($user->id_check_token)) {

                $params = [
                    'u'    => $user->id_check_token,
                    'type' => $certificateType,
                ];

                if ($training && !empty($training->id)) {
                    $params['training_id'] = $training->id;
                }

                $verificationUrl = \Illuminate\Support\Facades\URL::signedRoute(
                    'certificates.verify',
                    $params
                );

                // Generate as SVG (no Imagick required)
                $qrBase64 = base64_encode(
                    \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                        ->size(120)
                        ->margin(1)
                        ->generate($verificationUrl)
                );
            }
        @endphp

        <div class="certificate-page">

            {{-- Organisation name (optional: hide if paper already has it) --}}
            <div
                class="text-block org-name"
                data-block-key="org_name"
                style="left: {{ $coords['org_name']['x'] }}mm; top: {{ $coords['org_name']['y'] }}mm;"
            >
                {{ $certificate['orgName'] ?? 'Organisation Name' }}
            </div>

            {{-- "This is to certify..." --}}
            <div
                class="text-block certify-text"
                data-block-key="certify_text"
                style="left: {{ $coords['certify_text']['x'] }}mm; top: {{ $coords['certify_text']['y'] }}mm;"
            >
                This is to certify that
            </div>

            {{-- Recipient name --}}
            <div
                class="text-block recipient-name"
                data-block-key="recipient_name"
                style="left: {{ $coords['recipient_name']['x'] }}mm; top: {{ $coords['recipient_name']['y'] }}mm;"
            >
                {{ $certificate['recipientName'] ?? 'Recipient Name' }}
            </div>

            {{-- "has successfully completed..." --}}
            <div
                class="text-block course-title-text"
                data-block-key="course_title_text"
                style="left: {{ $coords['course_title_text']['x'] }}mm; top: {{ $coords['course_title_text']['y'] }}mm;"
            >
                has successfully completed the training course on
            </div>

            {{-- Course title --}}
            <div
                class="text-block course-title"
                data-block-key="course_title"
                style="left: {{ $coords['course_title']['x'] }}mm; top: {{ $coords['course_title']['y'] }}mm;"
            >
                {{ $certificate['courseTitle'] ?? 'Course Title' }}
            </div>

            {{-- Training details (date) --}}
            <div
                class="text-block training-details"
                data-block-key="training_details"
                style="left: {{ $coords['training_details']['x'] }}mm; top: {{ $coords['training_details']['y'] }}mm;"
            >
                {{ $certificate['dateLine'] ?? '' }}
            </div>

            @if(!empty($certificate['validLine']))
                <div
                    class="text-block training-details"
                    data-block-key="valid_line"
                    style="left: {{ $coords['training_details']['x'] }}mm; top: {{ ($coords['training_details']['y'] ?? 158) + 8 }}mm;"
                >
                    {{ $certificate['validLine'] }}
                </div>
            @endif

            @php
                $sign1Title = $certificate['defaultSign1'] ?? 'Signature 1';
                $sign2Title = $certificate['defaultSign2'] ?? 'Signature 2';
            @endphp

            {{-- Signature 1 --}}
            <div
                class="text-block signature-title"
                data-block-key="signature_1"
                style="left: {{ $coords['signature_1']['x'] }}mm; top: {{ $coords['signature_1']['y'] }}mm; white-space: normal; text-align: center;"
            >
                @if (!empty($certificate['sign1Image']))
                    <img src="{{ $certificate['sign1Image'] }}" alt="" style="max-height:40px; display:block; margin:0 auto 4px; border:none;">
                @endif
                @if (!empty($certificate['sign1Name']))
                    <div style="font-size:13px; text-align:center; margin-bottom:2px;">{{ $certificate['sign1Name'] }}</div>
                @endif
                {{ $sign1Title }}
            </div>

            {{-- Signature 2 --}}
            <div
                class="text-block signature-title"
                data-block-key="signature_2"
                style="left: {{ $coords['signature_2']['x'] }}mm; top: {{ $coords['signature_2']['y'] }}mm; white-space: normal; text-align: center;"
            >
                @if (!empty($certificate['sign2Image']))
                    <img src="{{ $certificate['sign2Image'] }}" alt="" style="max-height:40px; display:block; margin:0 auto 4px; border:none;">
                @endif
                @if (!empty($certificate['sign2Name']))
                    <div style="font-size:13px; text-align:center; margin-bottom:2px;">{{ $certificate['sign2Name'] }}</div>
                @endif
                {{ $sign2Title }}
            </div>

            {{-- Footer info: reference, printed by, date --}}
            <div
                class="text-block footer-info"
                data-block-key="footer_info"
                style="left: {{ $coords['footer_info']['x'] }}mm; top: {{ $coords['footer_info']['y'] }}mm;"
            >
                <div class="certificate-info">
                    Ref: {!! str_replace('/', '/<wbr>',  ($certificate['user']->user_id_reference ?? '—')) !!}
                    Printed by: {{ $certificate['footerProducer'] ?? 'System' }} <br>on {{ now()->format('Y-m-d') }}
                </div>
            </div>


            {{-- QR Code --}}
            <div
                class="text-block qr-placeholder"
                data-block-key="qr_code"
                style="left: {{ $coords['qr_code']['x'] }}mm; top: {{ $coords['qr_code']['y'] }}mm; @if($qrBase64) border: 0; @endif"
            >
                @if ($qrBase64)
                    <img src="data:image/svg+xml;base64, {{ $qrBase64 }}" alt="Verification QR code" style="width: 100%; height: 100%;">
                @else
                    QR
                @endif
            </div>

        </div>
    @endforeach
@else
    <div style="padding: 40px; text-align: center;">
        <h2>No Certificate Data</h2>
        <p>No data was provided to generate the certificate(s).</p>
        <p>Please <a href="{{ route('certificates.index') }}">go back</a> and select training records.</p>
    </div>
@endif

<script>
    (function () {
        const layoutStorageKey = 'nrcs_certificate_plain_layout_v1';

        // Initial layout from backend (PHP): coords + fontSizes
        const defaultLayout = @json($layout ?? ['coords' => [], 'fontSizes' => []]);

        const friendlyLabels = {
            org_name: 'Organisation name',
            certify_text: 'Certify text',
            recipient_name: 'Recipient name',
            course_title_text: 'Intro text before course title',
            course_title: 'Course title',
            training_details: 'Training details (date/validity)',
            signature_1: 'Signature 1 title',
            signature_2: 'Signature 2 title',
            footer_info: 'Footer reference / printed by',
            qr_code: 'QR Code',
        };

        // Add default visibility to defaultLayout, so reset works correctly.
        defaultLayout.visibility = {};
        Object.keys(friendlyLabels).forEach(function(key) {
            defaultLayout.visibility[key] = true;
        });

        // Current layout object (mutable copy)
        let layout = JSON.parse(JSON.stringify(defaultLayout));

        function loadLayoutFromStorage() {
            try {
                const raw = localStorage.getItem(layoutStorageKey);
                if (!raw) return;
                const parsed = JSON.parse(raw);
                if (parsed.coords) {
                    layout.coords = Object.assign({}, layout.coords, parsed.coords);
                }
                if (parsed.fontSizes) {
                    layout.fontSizes = Object.assign({}, layout.fontSizes, parsed.fontSizes);
                }
                if (parsed.visibility) {
                    layout.visibility = Object.assign({}, layout.visibility, parsed.visibility);
                }
            } catch (e) {
                console.warn('Could not load layout from storage:', e);
            }
        }

        function saveLayoutToStorage() {
            try {
                const data = JSON.stringify(layout);
                localStorage.setItem(layoutStorageKey, data);
                alert('Layout saved in this browser.');
            } catch (e) {
                console.error('Could not save layout:', e);
                alert('Could not save layout in this browser.');
            }
        }

        function resetLayout() {
            layout = JSON.parse(JSON.stringify(defaultLayout));
            applyLayoutToDom();
            buildEditorRows(); // refresh displayed values
        }

        function applyLayoutToDom() {
            // Apply coords, font sizes, and visibility to all elements
            Object.keys(friendlyLabels).forEach(function (key) {
                const blocks = document.querySelectorAll('[data-block-key="' + key + '"]');
                blocks.forEach(function (el) {
                    // Coords
                    const c = (layout.coords || {})[key];
                    if (c && typeof c.x !== 'undefined' && typeof c.y !== 'undefined') {
                        el.style.left = c.x + 'mm';
                        el.style.top  = c.y + 'mm';
                    }
                    // Font sizes
                    const fsMap = layout.fontSizes || {};
                    const fs    = fsMap[key];
                    if (fs) {
                        el.style.fontSize = fs + 'px';
                    }
                    // Visibility
                    const isVisible = (layout.visibility || {})[key];
                    if (typeof isVisible !== 'undefined') {
                        el.style.display = isVisible ? '' : 'none';
                    }
                });
            });
        }

        function adjustCoord(key, axis, delta) {
            if (!layout.coords[key]) {
                layout.coords[key] = {x: 0, y: 0};
            }
            layout.coords[key][axis] = (layout.coords[key][axis] || 0) + delta;
            applyLayoutToDom();
            updateEditorRowDisplay(key);
        }

        function adjustFontSize(key, delta) {
            if (!layout.fontSizes[key]) {
                // fall back to whatever is in defaultLayout or 14
                const defaultFs = (defaultLayout.fontSizes || {})[key] || 14;
                layout.fontSizes[key] = defaultFs;
            }
            layout.fontSizes[key] = Math.max(6, layout.fontSizes[key] + delta);
            applyLayoutToDom();
            updateEditorRowDisplay(key);
        }

        function toggleVisibility(key) {
            layout.visibility[key] = !layout.visibility[key];
            applyLayoutToDom();
            updateEditorRowDisplay(key);
        }

        function buildEditorRows() {
            const container = document.getElementById('layout-editor-rows');
            if (!container) return;
            container.innerHTML = '';

            const noSizeEditor = ['qr_code'];

            Object.keys(friendlyLabels).forEach(function (key) {
                const label = friendlyLabels[key] || key;
                const coord = layout.coords[key] || {x: 0, y: 0};
                const fs    = (layout.fontSizes && layout.fontSizes[key])
                    || (defaultLayout.fontSizes && defaultLayout.fontSizes[key])
                    || 14;
                const isVisible = (layout.visibility && typeof layout.visibility[key] !== 'undefined') ? layout.visibility[key] : true;

                const row = document.createElement('div');
                row.className = 'editor-row';
                row.dataset.editorKey = key;

                const sizeControls = !noSizeEditor.includes(key) ? `
                    <div class="editor-line">
                        <span class="axis-label">Size</span>
                        <button type="button" class="editor-btn" data-action="fs-minus">-</button>
                        <span class="value-fs">${fs}</span>
                        <button type="button" class="editor-btn" data-action="fs-plus">+</button>
                    </div>
                ` : '';

                row.innerHTML = `
                    <div class="editor-label">${label}</div>
                    <div class="editor-controls">

                        <div class="editor-line">
                            <span class="axis-label">X</span>
                            <button type="button" class="editor-btn" data-action="x-minus">-</button>
                            <span class="value-x">${coord.x}</span>
                            <button type="button" class="editor-btn" data-action="x-plus">+</button>
                        </div>

                        <div class="editor-line">
                            <span class="axis-label">Y</span>
                            <button type="button" class="editor-btn" data-action="y-minus">-</button>
                            <span class="value-y">${coord.y}</span>
                            <button type="button" class="editor-btn" data-action="y-plus">+</button>
                        </div>

                        ${sizeControls}

                        <div class="editor-line">
                            <span class="axis-label">Status</span>
                            <button type="button" class="editor-btn" data-action="toggle-vis">Toggle</button>
                            <span class="value-vis">${isVisible ? 'Visible' : 'Hidden'}</span>
                        </div>

                    </div>
                `;

                // Attach handlers
                row.querySelectorAll('button.editor-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const action = this.dataset.action;
                        if (action === 'x-minus') adjustCoord(key, 'x', -1);
                        if (action === 'x-plus')  adjustCoord(key, 'x', 1);
                        if (action === 'y-minus') adjustCoord(key, 'y', -1);
                        if (action === 'y-plus')  adjustCoord(key, 'y', 1);
                        if (action === 'fs-minus') adjustFontSize(key, -1);
                        if (action === 'fs-plus')  adjustFontSize(key, 1);
                        if (action === 'toggle-vis') toggleVisibility(key);
                    });
                });

                container.appendChild(row);
            });
        }

        function updateEditorRowDisplay(key) {
            const row = document.querySelector('.editor-row[data-editor-key="' + key + '"]');
            if (!row) return;
            const coord = layout.coords[key] || {x: 0, y: 0};
            const isVisible = (layout.visibility && typeof layout.visibility[key] !== 'undefined') ? layout.visibility[key] : true;

            const xSpan  = row.querySelector('.value-x');
            const ySpan  = row.querySelector('.value-y');
            const fsSpan = row.querySelector('.value-fs');
            const visSpan = row.querySelector('.value-vis');

            if (xSpan)  xSpan.textContent  = coord.x;
            if (ySpan)  ySpan.textContent  = coord.y;
            if (fsSpan) {
                const fs = (layout.fontSizes && layout.fontSizes[key])
                    || (defaultLayout.fontSizes && defaultLayout.fontSizes[key])
                    || 14;
                fsSpan.textContent = fs;
            }
            if (visSpan) visSpan.textContent = isVisible ? 'Visible' : 'Hidden';
        }

        function initToggle() {
            const toggleBtn = document.getElementById('toggle-layout-editor');
            const panel     = document.getElementById('layout-editor-panel');

            if (!toggleBtn || !panel) return;

            toggleBtn.addEventListener('click', function () {
                panel.classList.toggle('hidden');
            });
        }

        function initActions() {
            const saveBtn  = document.getElementById('save-layout-button');
            const resetBtn = document.getElementById('reset-layout-button');
            const infoBtn  = document.getElementById('info-layout-button');

            if (saveBtn) {
                saveBtn.addEventListener('click', saveLayoutToStorage);
            }
            if (resetBtn) {
                resetBtn.addEventListener('click', function () {
                    if (confirm('Reset all layout settings to defaults?')) {
                        resetLayout();
                    }
                });
            }
            if (infoBtn) {
                infoBtn.addEventListener('click', function () {
                    alert(
                        'Tips for alignment:\n\n' +
                        '- Print with cheap paper first.\n' +
                        '- Adjust X (left/right) and Y (up/down) until text lines up.\n' +
                        '- Save layout when you are happy.\n' +
                        '- Layout is stored only in this browser.'
                    );
                });
            }
        }

        // --- Init on DOM ready ---
        document.addEventListener('DOMContentLoaded', function () {
            loadLayoutFromStorage();
            applyLayoutToDom();
            buildEditorRows();
            initToggle();
            initActions();
        });
    })();
</script>

</body>
</html>
