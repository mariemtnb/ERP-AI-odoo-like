import axios from "axios";

const BASE_URL =
  import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000/api/v1";

export const api = axios.create({ baseURL: BASE_URL });

// --- token storage ---
const ACCESS_KEY = "erp_access";
const REFRESH_KEY = "erp_refresh";

export const tokens = {
  get access() {
    return localStorage.getItem(ACCESS_KEY);
  },
  get refresh() {
    return localStorage.getItem(REFRESH_KEY);
  },
  set(access: string, refresh?: string) {
    localStorage.setItem(ACCESS_KEY, access);
    if (refresh) localStorage.setItem(REFRESH_KEY, refresh);
  },
  clear() {
    localStorage.removeItem(ACCESS_KEY);
    localStorage.removeItem(REFRESH_KEY);
  },
};

api.interceptors.request.use((config) => {
  if (tokens.access) config.headers.Authorization = `Bearer ${tokens.access}`;
  return config;
});

// On 401: try one refresh, replay the request; on failure go to /login.
let refreshing: Promise<string> | null = null;

api.interceptors.response.use(
  (res) => res,
  async (error) => {
    const original = error.config;
    if (error.response?.status !== 401 || original._retried || !tokens.refresh) {
      throw error;
    }
    original._retried = true;
    try {
      refreshing ??= axios
        .post(`${BASE_URL}/auth/refresh/`, { refresh: tokens.refresh })
        .then((r) => {
          tokens.set(r.data.access, r.data.refresh);
          return r.data.access as string;
        })
        .finally(() => (refreshing = null));
      const access = await refreshing;
      original.headers.Authorization = `Bearer ${access}`;
      return api(original);
    } catch {
      tokens.clear();
      window.location.href = "/login";
      throw error;
    }
  }
);
