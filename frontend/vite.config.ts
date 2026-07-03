import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import path from "path";

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: { "@": path.resolve(__dirname, "./src") },
  },
  server: {
    host: true,
    port: 5173,
    // Docker bind mounts on Windows don't propagate FS events — poll instead.
    watch: { usePolling: true, interval: 500 },
  },
});
