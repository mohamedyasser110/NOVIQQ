"use client";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";

export default function LogoutButton() {
  const router = useRouter();
  const [email, setEmail] = useState<string | null>(null);

  useEffect(() => {
    try {
      const raw = localStorage.getItem("cos_user");
      setEmail(raw ? (JSON.parse(raw).email ?? null) : null);
    } catch {
      setEmail(null);
    }
  }, []);

  function logout() {
    localStorage.removeItem("cos_token");
    localStorage.removeItem("cos_user");
    router.push("/login");
  }

  if (!email) {
    return (
      <a href="/login" className="block rounded bg-orange-500 px-3 py-2 text-center text-sm font-bold text-black hover:bg-orange-400">
        تسجيل الدخول
      </a>
    );
  }

  return (
    <div className="rounded-lg border border-white/10 bg-white/5 p-3">
      <div className="mb-2 truncate text-xs text-slate-400" title={email}>{email}</div>
      <button
        onClick={logout}
        className="w-full rounded bg-white/10 px-3 py-1.5 text-sm font-bold text-slate-100 hover:bg-red-500 hover:text-black"
      >
        تسجيل الخروج
      </button>
    </div>
  );
}
