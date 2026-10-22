(function (window, document) {
    'use strict';
    const api = window.VNAddressPro;
    if (!api || typeof api.bindFields !== 'function' || api.initContactForm7) return;
    api.initContactForm7 = function (root = document) {
        if (!root || typeof root.querySelectorAll !== 'function') return [];
        const forms = [...root.querySelectorAll('form.wpcf7-form')];
        if (root.matches?.('form.wpcf7-form')) forms.unshift(root);
        const instances = [];
        for (const form of forms) {
            for (const ward of form.querySelectorAll('[data-vnap-cf7-kind="ward"]')) {
                const provinces = [...form.querySelectorAll('[data-vnap-cf7-kind="province"]')].filter(p => p.name === ward.dataset.vnapCf7Province);
                if (provinces.length !== 1 || [...form.elements].filter(p => p.name === ward.name).length !== 1) continue;
                const container = ward.closest('.vnap-cf7-wrap');
                if (!container) continue;
                container.dataset.vnapDefaultWard = ward.dataset.vnapDefaultWard || '';
                // Internal name mirrors are never submitted or used as server authority.
                const controls = { province: provinces[0], ward, provinceName: document.createElement('input'), wardName: document.createElement('input') };
                const instance = api.bindFields(container, controls);
                if (instance) instances.push(instance);
            }
        }
        return instances;
    };
    // Native form reset is already handled by the shared component. Invalid/spam/mail
    // failure events intentionally preserve selections. Explicit init supports inserted forms.
    document.addEventListener('wpcf7reset', event => api.initContactForm7(event.target));
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => api.initContactForm7(), { once: true });
    else api.initContactForm7();
})(window, document);
