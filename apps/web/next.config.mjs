/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  output: "export", // static hosting (Hostinger shared): `npm run build` -> out/
  images: { unoptimized: true },
  env: { NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:4100/api" },
};
export default nextConfig;
