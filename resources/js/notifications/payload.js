export function safeRoute(route, origin) {
    if (typeof route !== 'string' || !/^\/(?:notifications|messages(?:\/[1-9]\d*)?|(?:owner|renter)\/(?:tenancies|monthly-records|maintenance|applications)(?:\/[1-9]\d*)?)$/.test(route)) return null;
    const url = new URL(route, origin);
    return url.origin === origin ? url.pathname : null;
}

export function validPush(data, environment, binding, origin) {
    return Boolean(data && /^[1-9]\d*$/.test(data.notification_id) && data.environment === environment
        && binding && data.subscription_key === binding && safeRoute(data.route, origin)
        && typeof data.title === 'string' && data.title.length <= 100
        && typeof data.body === 'string' && data.body.length <= 200);
}

export function newInstallationId(cryptoApi = globalThis.crypto) {
    if (cryptoApi?.randomUUID) return cryptoApi.randomUUID();
    if (!cryptoApi?.getRandomValues) return null;
    const bytes = cryptoApi.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    const hex = [...bytes].map(byte => byte.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
