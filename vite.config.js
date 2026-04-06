import { defineConfig } from "vite";
import symfonyPlugin from "vite-plugin-symfony";

export default defineConfig({
    plugins: [
        symfonyPlugin(),
    ],symfony serve
    build: {
        rollupOptions: {
            input: {
                app: "./assets/app.js",
            },
        },
    },
});