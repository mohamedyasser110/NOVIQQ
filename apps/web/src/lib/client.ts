const BASE = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:4100/api";

export function getToken(): string | null {
  if (typeof window === "undefined") return null;
  return localStorage.getItem("cos_token");
}

export async function login(email: string, password: string) {
  const res = await fetch(`${BASE}/auth/login`, {
    method: "POST", headers: { "content-type": "application/json" },
    body: JSON.stringify({ email, password }),
  });
  if (!res.ok) throw new Error(`login failed (${res.status})`);
  const data = await res.json();
  localStorage.setItem("cos_token", data.accessToken);
  localStorage.setItem("cos_user", JSON.stringify(data.user));
  return data.user;
}

export async function api(path: string, init?: RequestInit) {
  const token = getToken();
  const res = await fetch(`${BASE}${path}`, {
    ...init, cache: "no-store",
    headers: { "content-type": "application/json", ...(token ? { authorization: `Bearer ${token}` } : {}), ...(init?.headers ?? {}) },
  });
  if (res.status === 401) { localStorage.removeItem("cos_token"); window.location.href = "/login"; throw new Error("unauthorized"); }
  if (!res.ok) throw new Error(`API ${res.status} ${path}`);
  return res.json();
}
