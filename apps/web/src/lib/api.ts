const BASE = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:4100/api";

export async function api(path: string, init?: RequestInit) {
  const res = await fetch(`${BASE}${path}`, { ...init, cache: "no-store", headers: { "content-type": "application/json", ...(init?.headers ?? {}) } });
  if (!res.ok) throw new Error(`API ${res.status} ${path}`);
  return res.json();
}
