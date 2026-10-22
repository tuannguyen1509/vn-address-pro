(function (window) {
    'use strict';
    const { createElement: h, useEffect, useRef, useState } = window.wp.element;
    const { useSelect, useDispatch } = window.wp.data;
    const { __ } = window.wp.i18n;
    const cartStore = 'wc/store/cart', checkoutStore = 'wc/store/checkout', validationStore = 'wc/store/validation';
    const config = window.VNAddressProBlocksConfig;
    const namespace = 'vn-address-pro';
    function wardComponent(prefix) {
        return function Ward({ checkoutExtensionData } = {}) {
            if (config.editor) return h('p', { className: 'vnap-block-preview' }, __('Chọn Phường / Xã / Đặc khu theo Tỉnh / Thành phố.', 'vn-address-pro'));
            const { address, cartReady, counterpartState, counterpartCountry, counterpartCity, extensionCodes, validationError, nativeStateError, same, saved } = useSelect(select => {
                const cart = select(cartStore), checkout = select(checkoutStore);
                const customer = cart.getCustomerData();
                const counterpart = customer[(prefix === 'shipping' ? 'billing' : 'shipping') + 'Address'];
                return {
                    address: customer[prefix + 'Address'],
                    cartReady: cart.hasFinishedResolution('getCartData'),
                    counterpartState: counterpart.state,
                    counterpartCountry: counterpart.country,
                    counterpartCity: counterpart.city,
                    extensionCodes: checkout.getExtensionData()?.[namespace] || {},
                    validationError: select(validationStore).getValidationError('vnap_' + prefix + '_ward'),
                    nativeStateError: select(validationStore).getValidationError((prefix === 'shipping' ? 'billing' : 'shipping') + '_state'),
                    same: checkout.getUseShippingAsBilling(),
                    saved: cart.getCartData().extensions?.[namespace]?.[prefix],
                };
            }, []);
            const { setExtensionData } = checkoutExtensionData;
            const { setValidationErrors, clearValidationError } = useDispatch(validationStore);
            const cartActions = useDispatch(cartStore);
            const [selection, setSelection] = useState({ state: address.state, code: '' });
            const [loaded, setLoaded] = useState({ province: '', items: [] });
            const [loading, setLoading] = useState(false);
            const [failed, setFailed] = useState(false);
            const [retry, setRetry] = useState(0);
            const initial = useRef({ state: null, pending: true });
            const synchronized = useRef('');
            const state = address.state;
            const province = address.country === 'VN' ? config.stateCodes[state] || '' : '';
            const rows = loaded.province === province ? loaded.items : [];
            const chosen = selection.state === state ? selection.code : '';
            const row = rows.find(item => item.code === chosen);
            const errorId = 'vnap_' + prefix + '_ward';
            const mirror = prefix === 'shipping' ? same : config.forcedBilling;
            const other = prefix === 'shipping' ? 'billing' : 'shipping';

            useEffect(() => {
                const controller = new AbortController();
                let current = true;
                if (province && initial.current.state === null) initial.current.state = state;
                else if (province && initial.current.state !== state) initial.current.pending = false;
                if (address.country !== 'VN') initial.current.pending = false;
                setSelection({ state, code: '' });
                setLoaded({ province: '', items: [] }); setFailed(false);
                if (!province) { setLoading(false); return () => controller.abort(); }
                setLoading(true);
                window.VNAddressProData.getWards(province, { signal: controller.signal }).then(items => {
                    if (!current) return;
                    setLoaded({ province, items }); setLoading(false);
                }).catch(() => { if (current) { setLoading(false); setFailed(true); } });
                return () => { current = false; controller.abort(); };
            }, [province, state, address.country, retry]);

            useEffect(() => {
                // Cart hydration and customer extension data can arrive after the ward response.
                // Empty provisional data must not consume the one-time restoration attempt.
                if (!initial.current.pending || initial.current.state !== state || !saved?.wardCode || saved.state !== state) return;
                if (loaded.province !== province) return;
                initial.current.pending = false;
                if (rows.some(item => item.code === saved.wardCode)) setSelection({ state, code: saved.wardCode });
            }, [state, saved, loaded]);

            useEffect(() => {
                const value = province && row ? row.code : '';
                if (extensionCodes[prefix + 'WardCode'] !== value) setExtensionData(namespace, prefix + 'WardCode', value);
                if (mirror && extensionCodes[other + 'WardCode'] !== value) setExtensionData(namespace, other + 'WardCode', value);
                if (address.country === 'VN') {
                    if (!row && !validationError) setValidationErrors({ [errorId]: { message: __('Vui lòng chọn Tỉnh / Thành phố và Phường / Xã / Đặc khu hợp lệ.', 'vn-address-pro'), hidden: false } });
                    else if (row && validationError) clearValidationError(errorId);
                    // A native state control removed by Woo's same-address toggle can leave its
                    // old error behind. Clear only when both real addresses match a validated VN selection.
                    if (mirror && row && nativeStateError && counterpartCountry === 'VN' && counterpartState === state) clearValidationError(other + '_state');
                    const city = row ? row.name : '';
                    const key = [state, value, mirror].join(':');
                    if (cartReady && synchronized.current !== key) {
                        synchronized.current = key;
                        if (address.city !== city) cartActions[prefix === 'billing' ? 'setBillingAddress' : 'setShippingAddress']({ city });
                        if (mirror && counterpartCity !== city) cartActions[other === 'billing' ? 'setBillingAddress' : 'setShippingAddress']({ city });
                        const current = window.wp.data.select(cartStore).getCustomerData();
                        const update = { [prefix + '_address']: { ...current[prefix + 'Address'], city } };
                        if (mirror) update[other + '_address'] = { ...current[other + 'Address'], city };
                        // This public action uses Woo's request queue and recalculation lifecycle.
                        // editing=true preserves newer local inputs while response totals/rates update.
                        // Woo owns failed-request notices; prevent an unhandled promise rejection here.
                        cartActions.updateCustomerData(update, true).catch(() => {});
                    }
                } else if (validationError) clearValidationError(errorId);
            }, [province, row, state, cartReady, address.country, address.city, counterpartCity, counterpartCountry, counterpartState, extensionCodes, validationError, nativeStateError, mirror, setExtensionData, setValidationErrors, clearValidationError]);
            useEffect(() => () => clearValidationError(errorId), [clearValidationError]);
            if (address.country !== 'VN') return null;
            return h('div', { className: 'vnap-address vnap-block-ward', 'aria-busy': loading, 'data-vnap-block-address': prefix },
                h('label', { className: 'vnap-label', htmlFor: errorId }, __('Phường / Xã / Đặc khu', 'vn-address-pro')),
                h('select', { id: errorId, className: 'vnap-select', 'data-vnap-province-code': loaded.province, value: row ? chosen : '', disabled: !province || loading || failed || loaded.province !== province, required: true, 'aria-required': true, 'aria-describedby': errorId + '_status', onChange: event => { initial.current.pending = false; setSelection({ state, code: event.target.value }); } },
                    h('option', { value: '' }, __('Chọn Phường / Xã / Đặc khu', 'vn-address-pro')),
                    rows.map(item => h('option', { key: item.code, value: item.code }, item.name))),
                h('p', { id: errorId + '_status', className: 'vnap-status', role: 'status', 'aria-live': 'polite' }, loading ? __('Đang tải danh sách Phường / Xã...', 'vn-address-pro') : failed ? __('Không thể tải dữ liệu. Vui lòng thử lại.', 'vn-address-pro') : ''),
                failed && h('button', { type: 'button', className: 'vnap-retry', onClick: () => setRetry(value => value + 1) }, __('Thử lại', 'vn-address-pro')));
        };
    }
    for (const metadata of config.blocks) {
        const prefix = metadata.name.includes('/billing-') ? 'billing' : 'shipping';
        window.wp.blocks.registerBlockType(metadata.name, { ...metadata, edit: wardComponent(prefix), save: () => null });
        window.wc.blocksCheckout.registerCheckoutBlock({ metadata, component: wardComponent(prefix), force: true });
    }
})(window);
