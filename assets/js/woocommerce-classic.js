(function ($, window, document) {
    'use strict';
    $(function () {
        if (!window.VNAddressPro || !window.VNAddressProWooConfig || !document.querySelector('form.checkout')) return;
        const config = window.VNAddressProWooConfig;
        const records = new Map();
        const saved = new Map();
        const body = $(document.body);
        const form = document.querySelector('form.checkout');

        function remove(prefix, clear) {
            const record = records.get(prefix);
            if (!record) return;
            if (record.instance) record.instance.destroy();
            record.root.remove();
            record.city.type = record.type;
            if (record.label) record.label.htmlFor = record.city.id;
            if (clear) { record.city.value = ''; record.code.value = ''; saved.delete(prefix); }
            records.delete(prefix);
        }
        function connect(prefix) {
            const country = document.getElementById(prefix + '_country');
            const state = document.getElementById(prefix + '_state');
            const city = document.getElementById(prefix + '_city');
            const codeField = document.getElementById('vnap_' + prefix + '_ward_code');
            let record = records.get(prefix);
            if (!country || country.value !== 'VN' || !city || city.tagName !== 'INPUT' || !state || !codeField || state.tagName !== 'SELECT') {
                remove(prefix, true);
                if (codeField) codeField.value = '';
                return;
            }
            if (record && (!record.root.isConnected || record.city !== city || record.state !== state || record.code !== codeField)) { remove(prefix, false); record = null; }
            const province = String(config.stateCodes[state.value] || '');
            if (record) {
                if (record.instance.getProvince().code !== province) {
                    saved.delete(prefix);
                    record.instance.setProvince(province);
                }
                return;
            }
            const root = document.createElement('div'); root.className = 'vnap-address vnap-wc-address'; root.setAttribute('data-vnap-address', '');
            const bridge = document.createElement('select'); bridge.setAttribute('data-vnap-province', ''); bridge.hidden = true;
            bridge.add(new Option('', ''));
            for (const option of state.options) {
                const value = config.stateCodes[option.value]; if (value) bridge.add(new Option(option.textContent, String(value)));
            }
            bridge.value = province;
            const ward = document.createElement('select'); ward.className = 'vnap-select'; ward.id = prefix + '_vnap_ward'; ward.setAttribute('data-vnap-ward', ''); ward.required = true; ward.setAttribute('aria-required', 'true');
            ward.add(new Option(config.placeholder, ''));
            const provinceName = document.createElement('input'); provinceName.type = 'hidden'; provinceName.setAttribute('data-vnap-province-name', '');
            const wardName = document.createElement('input'); wardName.type = 'hidden'; wardName.setAttribute('data-vnap-ward-name', '');
            const status = document.createElement('p'); status.id = ward.id + '_status'; status.className = 'vnap-status'; status.setAttribute('data-vnap-status', ''); status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite');
            const error = document.createElement('p'); error.id = ward.id + '_error'; error.className = 'vnap-error'; error.setAttribute('data-vnap-error', ''); error.setAttribute('role', 'alert');
            ward.setAttribute('aria-describedby', status.id + ' ' + error.id);
            const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'vnap-retry'; retry.setAttribute('data-vnap-retry', ''); retry.hidden = true; retry.textContent = window.VNAddressProConfig.strings.retry;
            root.append(bridge, ward, provinceName, wardName, status, error, retry);
            const previous = saved.get(prefix);
            root.dataset.vnapDefaultWard = codeField.value || (previous && previous.province_code === province ? previous.ward_code : '');
            const label = city.closest('.form-row')?.querySelector('label');
            const type = city.type; city.type = 'hidden';
            if (label) label.htmlFor = ward.id;
            city.insertAdjacentElement('afterend', root);
            record = { root, state, city, code: codeField, type, label, instance: null };
            records.set(prefix, record);
            root.addEventListener('vnap:ward-changed', event => {
                // Canonical code is separate; Woo's city remains a readable snapshot.
                codeField.value = event.detail.code;
                city.value = event.detail.name;
                saved.set(prefix, record.instance ? record.instance.getAddress() : { province_code: province, ward_code: event.detail.code });
            });
            root.addEventListener('change', event => {
                if (event.target === ward) {
                    $(city).trigger('change');
                    // Core's .address-field input handler schedules one checkout update.
                }
            });
            record.instance = window.VNAddressPro.init(root);
        }
        function reconnect() { for (const prefix of ['billing','shipping']) connect(prefix); }
        let scheduled = false;
        function schedule() {
            if (scheduled) return;
            scheduled = true;
            queueMicrotask(() => { scheduled = false; reconnect(); });
        }
        // Namespaced adapter hooks only; the vanilla core remains independent of jQuery.
        body.on('updated_checkout.vnap country_to_state_changed.vnap', schedule);
        $(form).on('change.vnap', '#billing_state,#shipping_state,#billing_country,#shipping_country', schedule);
        $(form).on('checkout_place_order.vnap', function () {
            for (const [prefix, record] of records) {
                if (prefix === 'shipping' && !document.querySelector('#ship-to-different-address-checkbox:checked')) continue;
                if (!record.instance.validate().valid) return false;
            }
            return true;
        });
        reconnect();
    });
})(jQuery, window, document);
