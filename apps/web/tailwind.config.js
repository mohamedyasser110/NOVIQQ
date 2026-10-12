/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./src/**/*.{ts,tsx}"],
  theme: {
    extend: {
      fontFamily: { sans: ["Cairo", "Inter", "system-ui", "sans-serif"] },
      colors: {
        navy: { 950: "#060F2B", 900: "#0B1F4D", 800: "#122A63", 700: "#1B3A7D" },
        brand: { 50: "#EEF4FF", 100: "#DCE7FD", 500: "#1D4ED8", 600: "#1A45BE", 700: "#163AA3" },
        accent: { 50: "#FFF4EB", 100: "#FFE6D1", 400: "#FB923C", 500: "#F97316", 600: "#EA6A0A" },
        paper: "#F4F6FB",
      },
      boxShadow: { card: "0 1px 3px rgba(11,31,77,.08), 0 4px 14px rgba(11,31,77,.06)" },
      borderRadius: { xl2: "1rem" },
    },
  },
  plugins: [],
};
