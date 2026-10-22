(function ($, window, document) {
    'use strict';
    $(function () {
        const config = window.VNAddressProEditorConfig;
        if (!config || !window.VNAddressProData) return;
        for (const prefix of ['billing', 'shipping']) {
            const admin = config.mode === 'admin', start = admin ? '_' : '';
            const code = document.getElementById(admin ? '_' + prefix + '_vnap_ward_code' : 'vnap_' + prefix + '_ward_code');
            const ward = document.getElementById('vnap_' + config.mode + '_' + prefix + '_ward');
            const city = document.getElementById(start + prefix + '_city');
            if (!code || !ward || !city || ward.dataset.vnapConnected) continue;
            if (admin && !code.value && code.dataset.vnapInitialCode) code.value = code.dataset.vnapInitialCode;
            ward.dataset.vnapConnected = '1';
            const cityWrap = city.closest('.form-row,.form-field');
            const originalRequired = city.required;
            const wardWrap = ward.closest('.form-row,.form-field');
            const status = document.createElement('span'); status.className = 'vnap-editor-status'; status.id = ward.id + '_status';
            status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite'); wardWrap.append(status); ward.setAttribute('aria-describedby', status.id);
            let controller, sequence = 0, previous, rows = [], scheduled = false, touched = false;
            function refresh() {
                const country = document.getElementById(start + prefix + '_country');
                const state = document.getElementById(start + prefix + '_state');
                if (!country || !state) return;
                const vn = country.value === 'VN', province = vn ? config.stateCodes[state.value] || '' : '';
                const fingerprint = [country.value, state.value, code.value].join(':');
                if (previous?.fingerprint === fingerprint && !ward.dataset.vnapFailed) return;
                const old = previous; previous = { country: country.value, state: state.value, code: code.value, fingerprint };
                if (old && old.fingerprint !== fingerprint) touched = true;
                if (old && (old.country !== country.value || old.state !== state.value) && old.code === code.value) {
                    code.value = ''; city.value = ''; previous.code = ''; previous.fingerprint = [country.value, state.value, ''].join(':');
                }
                if (!vn && code.value) { code.value = ''; previous.code = ''; previous.fingerprint = [country.value, state.value, ''].join(':'); }
                if (controller) controller.abort(); const current = ++sequence;
                rows = []; ward.replaceChildren(new Option(config.placeholder, '')); ward.disabled = true;
                ward.required = vn && (!admin || touched); wardWrap.hidden = !vn; cityWrap.hidden = vn; city.required = vn ? false : originalRequired;
                status.textContent = ''; delete ward.dataset.vnapFailed;
                if (!province) return;
                controller = new AbortController(); status.textContent = config.loading;
                window.VNAddressProData.getWards(province, { signal: controller.signal }).then(result => {
                    if (current !== sequence) return;
                    rows = result; for (const row of rows) ward.add(new Option(row.name, row.code));
                    ward.value = rows.some(row => row.code === code.value) ? code.value : '';
                    ward.disabled = false; ward.dataset.vnapProvinceCode = province;
                    status.textContent = ward.value ? '' : config.obsolete;
                }).catch(error => {
                    if (current !== sequence || error.name === 'AbortError') return;
                    status.textContent = config.error; ward.dataset.vnapFailed = '1';
                });
            }
            function schedule() { if (!scheduled) { scheduled = true; queueMicrotask(() => { scheduled = false; refresh(); }); } }
            ward.addEventListener('change', () => {
                touched = true;
                const row = rows.find(item => item.code === ward.value);
                code.value = row ? row.code : ''; city.value = row ? row.name : '';
                previous = undefined; status.textContent = ''; schedule();
            });
            $(document.body).on('change.vnapEditor', '#' + start + prefix + '_country,#' + start + prefix + '_state,#' + code.id, schedule);
            $(document.body).on('country_to_state_changed.vnapEditor', schedule);
            refresh();
        }
    });
})(jQuery, window, document);
