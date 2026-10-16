const API_ROOT = import.meta.env.VITE_API_URL || '/api';
const TOKEN_KEY = 'bhms-api-token';

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY) || sessionStorage.getItem(TOKEN_KEY);
}

export function saveToken(token: string | null): void {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token);
    sessionStorage.setItem(TOKEN_KEY, token);
  } else {
    localStorage.removeItem(TOKEN_KEY);
    sessionStorage.removeItem(TOKEN_KEY);
  }
}

export interface ApiOptions extends RequestInit {
  body?: any;
}

export interface PaginatedResponse<T> {
  data: T[];
  links?: {
    first?: string;
    last?: string;
    prev?: string | null;
    next?: string | null;
  };
  meta?: {
    current_page: number;
    from: number;
    last_page: number;
    path: string;
    per_page: number;
    to: number;
    total: number;
  };
}

export async function api<T = any>(path: string, options: ApiOptions = {}): Promise<T> {
  const headers = new Headers(options.headers || {});
  headers.set('Accept', 'application/json');
  headers.set('X-Requested-With', 'XMLHttpRequest');

  const token = getToken();
  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  let body = options.body;
  if (body && !(body instanceof FormData) && typeof body === 'object') {
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

  return payload as T;
}

export function listData<T>(payload: any): T[] {
  if (Array.isArray(payload?.data)) {
    return payload.data;
  }
  if (Array.isArray(payload)) {
    return payload;
  }
  return [];
}

export async function fetchAll<T>(path: string): Promise<T[]> {
  const [pathname, search = ''] = path.split('?');
  const params = new URLSearchParams(search);
  const first = await api<PaginatedResponse<T> | T[]>(`${pathname}?${params.toString()}`);

  const rows = listData<T>(first);
  const lastPage = Number((first as PaginatedResponse<T>)?.meta?.last_page || 1);

  if (lastPage <= 1) {
    return rows;
  }

  const remaining = await Promise.all(
    Array.from({ length: lastPage - 1 }, async (_, index) => {
      const pageParams = new URLSearchParams(search);
      pageParams.set('page', String(index + 2));
      const res = await api<PaginatedResponse<T>>(`${pathname}?${pageParams.toString()}`);
      return listData<T>(res);
    })
  );

  return rows.concat(...remaining.flat());
}

export const formatMoney = (amount: number | string | null | undefined): string => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(Number(amount || 0));
};

export const getTodayDateString = (): string => new Date().toISOString().slice(0, 10);
export const getCurrentMonthString = (): string => new Date().toISOString().slice(0, 7);

