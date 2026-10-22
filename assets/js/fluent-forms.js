(function (window, document, $) {
    'use strict';
    const api = window.VNAddressPro;
    if (!api || typeof api.bindFields !== 'function' || api.initFluentForms) return;
    api.initFluentForms = function (root = document) {
        if (!root || typeof root.querySelectorAll !== 'function') return [];
        const forms = [...root.querySelectorAll('form.frm-fluent-form')];
        if (root.matches?.('form.frm-fluent-form')) forms.unshift(root);
        const instances = [];
        for (const form of forms) {
            for (const ward of form.querySelectorAll('[data-vnap-fluent-kind="ward"]')) {
                const provinces = [...form.querySelectorAll('[data-vnap-fluent-kind="province"]')].filter(p => p.name === ward.dataset.vnapFluentProvince);
                if (provinces.length !== 1 || [...form.elements].filter(p => p.name === ward.name).length !== 1) continue;
                const container = ward.closest('.vnap-fluent-wrap');
                if (!container) continue;
                const instance = api.bindFields(container, {province: provinces[0], ward, provinceName: document.createElement('input'), wardName: document.createElement('input')});
                if (instance) instances.push(instance);
            }
        }
        return instances;
    };
    // Official Fluent initialization event handles its own dynamically initialized forms.
    // Native reset is handled by the shared AddressField; validation errors preserve selections.
    if ($) $(document.body).on('fluentform_init.vnap', (event, form) => api.initFluentForms(form?.[0] || document));
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => api.initFluentForms(), {once: true});
    else api.initFluentForms();
})(window, document, window.jQuery);
