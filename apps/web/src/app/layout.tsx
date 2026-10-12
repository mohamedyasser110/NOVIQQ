import type { Metadata } from "next";
import "./globals.css";
import Shell, { AuthGuard } from "./shell";

export const metadata: Metadata = { title: "noviq solutions", description: "نظام الشركة الداخلي — noviq solutions" };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ar" dir="rtl">
      <body className="min-h-screen bg-paper font-sans text-navy-900">
        <AuthGuard />
        <Shell>{children}</Shell>
      </body>
    </html>
  );
}
