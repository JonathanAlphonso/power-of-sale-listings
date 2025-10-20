import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    server: {
        host: true,
        port: 5173,
        strictPort: true, // fail fast if taken, so you know to free it
        hmr: { host: "localhost" }, // WSL/Herd friendly
        watch: { usePolling: true }, // needed on /mnt/c; remove if you move repo into ~/code
    },
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
