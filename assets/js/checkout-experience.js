(function ($, document) {
    'use strict';
    const config = window.VNAPCheckoutExperience || {};
    function address(prefix) {
        const country = document.getElementById(prefix + '_country');
        const parent = document.querySelector('.woocommerce-' + prefix + '-fields__field-wrapper');
        if (!country || !parent) return;
        const transport = document.getElementById('vnap_' + prefix + '_ward_code_field');
        if (transport) { transport.hidden = true; transport.style.display = 'none'; }
        let group = parent.querySelector('[data-vnap-address-cluster]');
        if (!group) {
            group = document.createElement('fieldset');
            group.className = 'form-row form-row-wide vnap-checkout-address';
            group.dataset.vnapAddressCluster = prefix;
            const legend = document.createElement('legend'); legend.textContent = config.address;
            group.appendChild(legend);
            const first = document.getElementById(prefix + '_country_field');
            if (!first || first.parentNode !== parent) return;
            first.before(group);
        }
        for (const key of ['country', 'state', 'city', 'address_1']) {
            const row = document.getElementById(prefix + '_' + key + '_field');
            if (row && row.parentNode !== group) group.appendChild(row);
        }
        let details = group.querySelector('details');
        if (!details) {
            details = document.createElement('details'); details.className = 'vnap-address-details';
            const summary = document.createElement('summary'); summary.textContent = config.details;
            details.appendChild(summary); group.appendChild(details);
        }
        // Preserve optional details, and expose required foreign fields rather than dropping them.
        let count = 0, open = country.value !== 'VN';
        for (const key of ['address_2', 'postcode']) {
            const row = document.getElementById(prefix + '_' + key + '_field');
            if (!row) continue;
            ++count; if (row.parentNode !== details) details.appendChild(row);
            const input = row.querySelector('input'); if (input?.value || input?.required || row.classList.contains('validate-required')) open = true;
        }
        details.hidden = !count;
        if (open) details.open = true;
        group.appendChild(details);
    }
    function vat() {
        const toggle = document.getElementById('vnap_vat_requested');
        const fields = document.querySelector('[data-vnap-vat-fields]');
        if (!toggle || !fields) return;
        fields.hidden = !toggle.checked;
        fields.style.display = toggle.checked ? '' : 'none'; // Functional state, independent of optional theme CSS.
        for (const input of fields.querySelectorAll('input,textarea')) {
            input.disabled = !toggle.checked;
            input.required = toggle.checked && input.dataset.vnapVatRequired === '1';
        }
        toggle.setAttribute('aria-expanded', String(toggle.checked));
    }
    function refresh() {
        if (config.compact) {
            address('billing'); address('shipping');
            const notes = document.getElementById('order_comments_field');
            if (notes && !notes.closest('[data-vnap-order-notes]')) {
                const details = document.createElement('details'); details.dataset.vnapOrderNotes = '';
                const summary = document.createElement('summary'); summary.textContent = config.notes;
                notes.before(details); details.appendChild(summary); details.appendChild(notes);
                if (notes.querySelector('textarea')?.value || notes.querySelector('textarea')?.required || notes.classList.contains('validate-required')) details.open = true;
            }
        }
        vat();
    }
    $(refresh);
    $(document.body).on('updated_checkout country_to_state_changed', refresh);
    document.addEventListener('change', function (event) {
        if (event.target.id === 'vnap_vat_requested') vat();
        if (event.target.id === 'billing_country' || event.target.id === 'shipping_country') refresh();
    });
})(window.jQuery, document);
