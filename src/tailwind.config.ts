import type { Config } from "tailwindcss"

export default {
  content: ["./app/**/*.{ts,tsx}"],
  theme: {},
  plugins: [require("tailwindcss-animate")],
} satisfies Config