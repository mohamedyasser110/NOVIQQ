"use client";
import { useEffect } from "react";
import { usePathname, useRouter } from "next/navigation";

/** Client-side route guard: token lives in localStorage, so no-token
 *  users are pushed to /login on every navigation (except /login itself). */
export default function AuthGuard() {
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    if (pathname === "/login") return;
    try {
      if (!localStorage.getItem("cos_token")) router.push("/login");
    } catch {
      router.push("/login");
    }
  }, [pathname, router]);

  return null;
}
