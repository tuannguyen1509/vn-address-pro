(function (window) {
    'use strict';
    const cache = new Map(); // Fulfilled, validated responses only; page lifetime.
    const code = value => /^(?:0*[1-9][0-9]*)$/.test(String(value)) && Number.isSafeInteger(Number(value)) ? String(Number(value)) : '';
    async function getWards(value, { signal } = {}) {
        const chosen = code(value);
        if (!chosen) throw new Error('province');
        const config = window.VNAddressProConfig || {};
        const url = new URL(config.wardsUrl, window.location.href);
        if (url.origin !== window.location.origin || !/^https?:$/.test(url.protocol) || url.username || url.password) throw new Error('invalid-url');
        url.searchParams.set('province_code', chosen);
        if (signal?.aborted) throw new DOMException('Cancelled', 'AbortError');
        if (cache.has(url.href)) return cache.get(url.href);
        const controller = new AbortController();
        const abort = () => controller.abort();
        signal?.addEventListener('abort', abort, { once: true });
        const timeout = window.setTimeout(abort, 20000);
        try {
            const response = await window.fetch(url.href, { signal: controller.signal, credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('request');
            const result = await response.json();
            if (!result || result.success !== true || !result.province || code(result.province.code) !== chosen || !Array.isArray(result.data) || result.count !== result.data.length) throw new Error('schema');
            const seen = new Set();
            const rows = Object.freeze(result.data.map(row => {
                if (!row || !code(row.code) || code(row.province_code) !== chosen || typeof row.name !== 'string' || !row.name.trim() || seen.has(code(row.code))) throw new Error('schema');
                seen.add(code(row.code));
                return Object.freeze({ code: code(row.code), name: row.name });
            }));
            if (controller.signal.aborted || signal?.aborted) throw new DOMException('Cancelled', 'AbortError');
            cache.set(url.href, rows);
            return rows;
        } finally {
            window.clearTimeout(timeout);
            signal?.removeEventListener('abort', abort);
        }
    }
    window.VNAddressProData = Object.freeze({ code, getWards });
})(window);
