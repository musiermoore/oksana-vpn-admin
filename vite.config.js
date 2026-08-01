import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import Components from 'unplugin-vue-components/vite';
import { PrimeVueResolver } from '@primevue/auto-import-resolver';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const vitePort = Number(env.VITE_PORT || 5173);
    const hmrHost = env.VITE_HMR_HOST || 'localhost';
    const hmrPort = Number(env.VITE_HMR_PORT || vitePort);

    return {
        plugins: [
            vue(),
            Components({
                dirs: ['resources/js/Shared', 'resources/js/Shared/Admin'],
                resolvers: [PrimeVueResolver()],
            }),
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
        ],
        server: {
            host: '0.0.0.0',
            port: vitePort,
            strictPort: true,
            watch: {
                usePolling: true,
            },
            hmr: {
                host: hmrHost,
                port: hmrPort,
            },
        },
        preview: {
            host: '0.0.0.0',
            port: vitePort,
            strictPort: true,
        },
        build: {
            rollupOptions: {
                output: {
                    manualChunks(id) {
                        if (!id.includes('node_modules')) {
                            return;
                        }

                        if (id.includes('primevue') || id.includes('@primeuix') || id.includes('primeicons')) {
                            return 'primevue-vendor';
                        }

                        if (id.includes('@fortawesome')) {
                            return 'fontawesome-vendor';
                        }

                        if (id.includes('@inertiajs')) {
                            return 'inertia-vendor';
                        }

                        if (id.includes('@vueuse')) {
                            return 'vueuse-vendor';
                        }

                        if (id.includes('vue')) {
                            return 'vue-vendor';
                        }
                    },
                },
            },
        },
    };
});
