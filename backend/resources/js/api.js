const API_ROOT = '/api';
const TOKEN_KEY = 'bhms-api-token';

export function getToken() {
    return sessionStorage.getItem(TOKEN_KEY);
}

export function saveToken(token) {
    if (token) sessionStorage.setItem(TOKEN_KEY, token);
    else sessionStorage.removeItem(TOKEN_KEY);
}

export async function api(path, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');

    const token = getToken();
    if (token) headers.set('Authorization', `Bearer ${token}`);

    let body = options.body;
    if (body && !(body instanceof FormData)) {
        headers.set('Content-Type', 'application/json');
        body = JSON.stringify(body);
    }

    const response = await fetch(`${API_ROOT}${path}`, {
        ...options,
        headers,
        body,
    });

    const payload = response.status === 204 ? null : await response.json().catch(() => ({}));
    if (!response.ok) {
        if (response.status === 401) {
            saveToken(null);
            window.dispatchEvent(new Event('bhms:unauthorized'));
        }
        const validation = payload?.errors
            ? Object.values(payload.errors).flat().join(' ')
            : payload?.message;
        throw new Error(validation || `Request failed (${response.status}).`);
    }
    return payload;
}

export function listData(payload) {
    return Array.isArray(payload?.data) ? payload.data : [];
}

export async function fetchAll(path) {
    const [pathname, search = ''] = path.split('?');
    const params = new URLSearchParams(search);
    const first = await api(`${pathname}?${params.toString()}`);
    const rows = listData(first);
    const lastPage = Number(first?.meta?.last_page || 1);
    if (lastPage <= 1) return rows;

    const remaining = await Promise.all(Array.from({ length: lastPage - 1 }, async (_, index) => {
        const pageParams = new URLSearchParams(search);
        pageParams.set('page', String(index + 2));
        return listData(await api(`${pathname}?${pageParams.toString()}`));
    }));
    return rows.concat(...remaining);
}
