(function (window, document) {
    'use strict';
    if (window.VNAddressPro && window.VNAddressPro.init) return;
    const instances = new WeakMap();
    const config = window.VNAddressProConfig || {};
    const strings = config.strings || {};
    const selector = '[data-vnap-address]';
    const code = value => /^(?:0*[1-9][0-9]*)$/.test(String(value)) && Number.isSafeInteger(Number(value)) ? String(Number(value)) : '';

    function emit(container, name, detail) {
        container.dispatchEvent(new CustomEvent('vnap:' + name, { bubbles: true, detail }));
    }

    class AddressField {
        constructor(container, controls = null) {
            this.container = container;
            this.province = controls ? controls.province : container.querySelector('[data-vnap-province]');
            this.ward = controls ? controls.ward : container.querySelector('[data-vnap-ward]');
            this.provinceName = controls ? controls.provinceName : container.querySelector('[data-vnap-province-name]');
            this.wardName = controls ? controls.wardName : container.querySelector('[data-vnap-ward-name]');
            this.address = container.querySelector('[data-vnap-address-line]');
            this.status = container.querySelector('[data-vnap-status]');
            this.error = container.querySelector('[data-vnap-error]');
            this.retry = container.querySelector('[data-vnap-retry]');
            this.placeholder = this.ward.options[0].textContent;
            this.sequence = 0;
            this.destroyed = false;
            this.wards = [];
            this.loadedProvince = '';
            this.controller = null;
            this.onProvince = () => { this.setProvince(this.province.value); };
            this.onWard = () => { this.setWard(this.ward.value); };
            this.onRetry = () => { this.setProvince(this.province.value); };
            this.onReset = event => { queueMicrotask(() => { if (!this.destroyed && !event.defaultPrevented) this.restoreDefaults(); }); };
            this.onAddress = () => { this.address.removeAttribute('aria-invalid'); };
            this.province.addEventListener('change', this.onProvince);
            this.ward.addEventListener('change', this.onWard);
            if (this.retry) this.retry.addEventListener('click', this.onRetry);
            if (this.address) this.address.addEventListener('input', this.onAddress);
            this.form = this.province.form;
            if (this.form) this.form.addEventListener('reset', this.onReset);
            this.defaultProvince = this.province.value;
            this.defaultWard = code(container.dataset.vnapDefaultWard || '');
            this.defaultAddress = this.address ? this.address.value : '';
            this.ready = Promise.resolve().then(() => this.restoreDefaults()).then(() => {
                if (!this.destroyed) emit(this.container, 'ready', this.getAddress());
                return this;
            });
        }

        getProvince() {
            if (this.destroyed) return { code: '', name: '' };
            const option = this.province.selectedOptions[0];
            return { code: code(this.province.value), name: this.province.value && option ? option.textContent : '' };
        }
        getWard() {
            if (this.destroyed) return { code: '', name: '', provinceCode: '' };
            const row = this.loadedProvince === this.province.value && this.wards.find(item => String(item.code) === this.ward.value);
            return { code: row ? String(row.code) : '', name: row ? row.name : '', provinceCode: this.getProvince().code };
        }
        getAddress() {
            const p = this.getProvince(), w = this.getWard();
            return { province_code: p.code, province_name: p.name, ward_code: w.code, ward_name: w.name, address: this.address ? this.address.value : '' };
        }
        clearWard() {
            this.ward.replaceChildren(new Option(this.placeholder, ''));
            this.ward.disabled = true;
            this.ward.removeAttribute('aria-invalid');
            this.wardName.value = '';
            this.wards = [];
            this.loadedProvince = '';
        }
        announce(text = '') { if (this.status) this.status.textContent = text; }
        fail(kind, message) {
            if (this.error) this.error.textContent = message || '';
            if (this.retry) this.retry.hidden = kind !== 'request';
            this.ward.setAttribute('aria-invalid', 'true');
            emit(this.container, 'error', { code: kind, message: message || '', provinceCode: this.getProvince().code });
        }
        async setProvince(value, desiredWard = '') {
            if (this.destroyed) return false;
            const current = ++this.sequence;
            if (this.controller) this.controller.abort();
            this.controller = null;
            this.clearWard();
            this.province.removeAttribute('aria-invalid');
            if (this.error) this.error.textContent = '';
            if (this.retry) this.retry.hidden = true;
            this.announce();
            const chosen = code(value);
            this.province.value = [...this.province.options].some(option => option.value === chosen) ? chosen : '';
            this.provinceName.value = this.getProvince().name;
            emit(this.container, 'province-changed', this.getProvince());
            if (this.destroyed || current !== this.sequence) return false;
            emit(this.container, 'ward-changed', this.getWard());
            if (this.destroyed || current !== this.sequence) return false;
            if (!this.province.value) {
                this.container.removeAttribute('aria-busy');
                if (desiredWard) this.fail('invalid-ward', strings.invalidWard);
                return false;
            }
            this.container.setAttribute('aria-busy', 'true');
            this.announce(strings.loading);
            emit(this.container, 'wards-loading', { provinceCode: chosen });
            if (this.destroyed || current !== this.sequence) return false;
            try {
                const controller = new AbortController();
                this.controller = controller;
                const rows = await window.VNAddressProData.getWards(chosen, { signal: controller.signal });
                if (this.destroyed || current !== this.sequence) return false;
                this.wards = rows;
                this.loadedProvince = chosen;
                for (const row of rows) this.ward.add(new Option(row.name, row.code));
                this.ward.disabled = rows.length === 0;
                this.announce(rows.length ? strings.loaded : strings.empty);
                emit(this.container, 'wards-loaded', { provinceCode: chosen, count: rows.length });
                if (this.destroyed || current !== this.sequence) return false;
                if (desiredWard) this.setWard(desiredWard);
                return true;
            } catch (error) {
                if (!this.destroyed && current === this.sequence) {
                    this.clearWard();
                    this.announce();
                    this.fail('request', strings.error);
                }
                return false;
            } finally {
                if (!this.destroyed && current === this.sequence) {
                    this.controller = null;
                    this.container.removeAttribute('aria-busy');
                }
            }
        }
        setWard(value) {
            if (this.destroyed) return false;
            const wanted = code(value);
            const row = this.loadedProvince === this.province.value && this.wards.find(item => item.code === wanted);
            this.ward.value = row ? wanted : '';
            this.wardName.value = row ? row.name : '';
            this.ward.removeAttribute('aria-invalid');
            if (this.error) this.error.textContent = '';
            if (wanted && !row) this.fail('invalid-ward', strings.invalidWard);
            if (!this.destroyed) emit(this.container, 'ward-changed', this.getWard());
            return !!row;
        }
        async restoreDefaults() {
            if (this.destroyed) return false;
            if (this.address) this.address.value = this.defaultAddress;
            return this.setProvince(this.defaultProvince, this.defaultWard);
        }
        reset() { return this.restoreDefaults(); }
        validate() {
            if (this.destroyed) return { valid: false, errors: [] };
            const errors = [];
            const fields = [[this.province, 'province_code', !!this.getProvince().code && !this.province.disabled], [this.ward, 'ward_code', !!this.getWard().code], [this.address, 'address', !!(this.address && this.address.value.trim())]];
            for (const [field, key, filled] of fields) {
                if (!field) continue;
                const invalid = field.required && !filled;
                field.setAttribute('aria-invalid', String(invalid));
                if (invalid) errors.push({ field: key, message: strings.required || '' });
            }
            if (this.error) this.error.textContent = errors.length ? strings.required || '' : '';
            return { valid: errors.length === 0, errors };
        }
        destroy() {
            if (this.destroyed) return;
            this.destroyed = true;
            ++this.sequence;
            if (this.controller) this.controller.abort();
            this.province.removeEventListener('change', this.onProvince);
            this.ward.removeEventListener('change', this.onWard);
            if (this.form) this.form.removeEventListener('reset', this.onReset);
            if (this.retry) this.retry.removeEventListener('click', this.onRetry);
            if (this.address) this.address.removeEventListener('input', this.onAddress);
            this.container.removeAttribute('aria-busy');
            instances.delete(this.container);
            this.wards = [];
            this.controller = null;
            for (const name of ['container','province','ward','provinceName','wardName','address','status','error','retry','form']) this[name] = null;
        }
    }

    const api = window.VNAddressPro = window.VNAddressPro || {};
    // Adapter entrypoint: explicit native controls, shared lifecycle/cache/race handling.
    api.bindFields = function (container, controls) {
        if (!container || !container.dispatchEvent || !controls || controls.province?.tagName !== 'SELECT' || controls.ward?.tagName !== 'SELECT' || !controls.ward.options.length || controls.provinceName?.tagName !== 'INPUT' || controls.wardName?.tagName !== 'INPUT' || controls.province.form !== controls.ward.form) return null;
        if (instances.has(container)) return instances.get(container);
        const instance = new AddressField(container, controls);
        instances.set(container, instance);
        return instance;
    };
    api.init = function (options) {
        const container = options && options.container ? options.container : options;
        if (!container || !container.matches || !container.matches(selector)) return null;
        if (instances.has(container)) return instances.get(container);
        for (const field of ['province', 'ward', 'province-name', 'ward-name']) {
            if (!container.querySelector('[data-vnap-' + field + ']')) return null;
        }
        if (['province','ward'].some(field => container.querySelector('[data-vnap-' + field + ']').tagName !== 'SELECT')) return null;
        if (['province-name','ward-name'].some(field => container.querySelector('[data-vnap-' + field + ']').tagName !== 'INPUT')) return null;
        const address = container.querySelector('[data-vnap-address-line]');
        if (address && address.tagName !== 'INPUT') return null;
        if (!container.querySelector('[data-vnap-ward]').options?.length) return null;
        const instance = new AddressField(container);
        instances.set(container, instance);
        return instance;
    };
    api.getInstance = container => instances.get(container) || null;
    api.initAll = function (root = document) {
        const elements = [...root.querySelectorAll(selector)];
        if (root.matches && root.matches(selector)) elements.unshift(root);
        return elements.map(container => api.init(container)).filter(Boolean);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => api.initAll(), { once: true });
    else api.initAll();
})(window, document);
