"use client";
import React from "react";

function Svg({ children, size = 18 }: { children: React.ReactNode; size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" aria-hidden>
      {children}
    </svg>
  );
}

const D = ({ size }: { size?: number }) => (
  <Svg size={size}><rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" /></Svg>
);
const Brief = ({ size }: { size?: number }) => (
  <Svg size={size}><rect x="2" y="7" width="20" height="13" rx="2" /><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" /><line x1="2" y1="12" x2="22" y2="12" /></Svg>
);
const Kanban = ({ size }: { size?: number }) => (
  <Svg size={size}><rect x="3" y="3" width="5" height="18" rx="1" /><rect x="10" y="3" width="5" height="11" rx="1" /><rect x="17" y="3" width="5" height="15" rx="1" /></Svg>
);
const Cal = ({ size }: { size?: number }) => (
  <Svg size={size}><rect x="3" y="4" width="18" height="17" rx="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /></Svg>
);
const Users = ({ size }: { size?: number }) => (
  <Svg size={size}><circle cx="9" cy="7" r="3.5" /><path d="M2.5 20c.8-3.5 3.4-5.5 6.5-5.5s5.7 2 6.5 5.5" /><circle cx="17" cy="8.5" r="2.5" /><path d="M16 14.7c2.6.3 4.6 2 5.3 4.8" /></Svg>
);
const Building = ({ size }: { size?: number }) => (
  <Svg size={size}><rect x="4" y="3" width="16" height="18" rx="1" /><line x1="9" y1="7" x2="10" y2="7" /><line x1="14" y1="7" x2="15" y2="7" /><line x1="9" y1="11" x2="10" y2="11" /><line x1="14" y1="11" x2="15" y2="11" /><line x1="10" y1="21" x2="10" y2="17" /><line x1="14" y1="21" x2="14" y2="17" /></Svg>
);
const Receipt = ({ size }: { size?: number }) => (
  <Svg size={size}><path d="M5 3h14v18l-2.3-1.5L14.4 21l-2.4-1.5L9.6 21l-2.3-1.5L5 21z" /><line x1="9" y1="8" x2="15" y2="8" /><line x1="9" y1="12" x2="15" y2="12" /></Svg>
);
const Pie = ({ size }: { size?: number }) => (
  <Svg size={size}><circle cx="12" cy="12" r="9" /><path d="M12 3v9h9" /></Svg>
);
const Bank = ({ size }: { size?: number }) => (
  <Svg size={size}><path d="M3 9.5 12 4l9 5.5" /><line x1="5" y1="10" x2="5" y2="18" /><line x1="10" y1="10" x2="10" y2="18" /><line x1="14" y1="10" x2="14" y2="18" /><line x1="19" y1="10" x2="19" y2="18" /><line x1="3" y1="20.5" x2="21" y2="20.5" /></Svg>
);
const Cash = ({ size }: { size?: number }) => (
  <Svg size={size}><rect x="2" y="6" width="20" height="12" rx="2" /><circle cx="12" cy="12" r="2.5" /><line x1="6" y1="12" x2="6.5" y2="12" /><line x1="17.5" y1="12" x2="18" y2="12" /></Svg>
);
const Chart = ({ size }: { size?: number }) => (
  <Svg size={size}><line x1="3" y1="3" x2="3" y2="21" /><line x1="3" y1="21" x2="21" y2="21" /><line x1="8" y1="16" x2="8" y2="12" /><line x1="13" y1="16" x2="13" y2="8" /><line x1="18" y1="16" x2="18" y2="5" /></Svg>
);
const Shield = ({ size }: { size?: number }) => (
  <Svg size={size}><path d="M12 2 4 5.5V11c0 5 3.4 8.8 8 11 4.6-2.2 8-6 8-11V5.5z" /><path d="m8.8 11.5 2.2 2.2 4.2-4.5" /></Svg>
);
const Sliders = ({ size }: { size?: number }) => (
  <Svg size={size}><line x1="4" y1="7" x2="20" y2="7" /><circle cx="15" cy="7" r="2" /><line x1="4" y1="17" x2="20" y2="17" /><circle cx="9" cy="17" r="2" /></Svg>
);
const Out = ({ size }: { size?: number }) => (
  <Svg size={size}><path d="M14 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8" /><path d="m10 12 5 0" /><path d="m13 9 3 3-3 3" /></Svg>
);
const Menu = ({ size }: { size?: number }) => (
  <Svg size={size}><line x1="4" y1="7" x2="20" y2="7" /><line x1="4" y1="12" x2="20" y2="12" /><line x1="4" y1="17" x2="20" y2="17" /></Svg>
);
const Chev = ({ size }: { size?: number }) => (
  <Svg size={size}><path d="m14 6-6 6 6 6" /></Svg>
);
const Clock = ({ size }: { size?: number }) => (
  <Svg size={size}><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3.5 2" /></Svg>
);
const Check = ({ size }: { size?: number }) => (
  <Svg size={size}><circle cx="12" cy="12" r="9" /><path d="m8 12.5 2.7 2.7L16.5 9" /></Svg>
);
const Wallet = ({ size }: { size?: number }) => (
  <Svg size={size}><path d="M3 7a2 2 0 0 1 2-2h13a1 1 0 0 1 1 1v2" /><rect x="3" y="7" width="18" height="13" rx="2" /><circle cx="16.5" cy="13.5" r="1.2" /></Svg>
);
const Pen = ({ size }: { size?: number }) => (
  <Svg size={size}><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" /></Svg>
);
const Folder = ({ size }: { size?: number }) => (
  <Svg size={size}><path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /></Svg>
);
const User = ({ size }: { size?: number }) => (
  <Svg size={size}><circle cx="12" cy="8" r="3.5" /><path d="M5 20c.9-3.8 3.7-6 7-6s6.1 2.2 7 6" /></Svg>
);

/** name → icon component (size inherits via prop). */
export const ICONS: Record<string, (p: { size?: number }) => React.ReactElement> = {
  dashboard: D, projects: Brief, tasks: Kanban, timeline: Cal, team: Users,
  clients: Building, invoices: Receipt, allocations: Pie, treasury: Bank, payouts: Cash,
  reports: Chart, audit: Shield, settings: Sliders,
  clock: Clock, check: Check, wallet: Wallet, pen: Pen, folder: Folder, cash: Cash, chart: Chart, menu: Menu, chev: Chev, user: User,
};

export function Icon({ name, size = 18 }: { name: string; size?: number }) {
  const C = ICONS[name] ?? D;
  return <C size={size} />;
}
