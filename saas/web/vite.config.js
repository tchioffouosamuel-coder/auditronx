import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import { defineConfig } from "vite";

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    // Une seule cible : l'API est unique pour tous les établissements, et
    // l'en-tête X-Tenant (ajouté par src/lib/api.js) désigne lequel.
    proxy: {
      "/api": {
        target: process.env.AUDITRON_API_PROXY || "http://localhost:8000",
        changeOrigin: true,
      },
    },
  },
});
