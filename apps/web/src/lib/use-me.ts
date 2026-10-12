"use client";
import { useEffect, useState } from "react";
import { api } from "./client";

/** Current user's effective permission keys (fail-closed: hidden until loaded). */
export function useMe() {
  const [perms, setPerms] = useState<string[] | null>(null);
  useEffect(() => {
    let live = true;
    api("/users/me")
      .then((m) => { if (live) setPerms(Array.isArray(m.permissions) ? m.permissions : []); })
      .catch(() => { if (live) setPerms([]); });
    return () => { live = false; };
  }, []);
  return { perms, can: (k: string) => perms?.includes(k) ?? false };
}
