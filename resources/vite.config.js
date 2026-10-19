import { defineConfig } from 'vite';

// Each entry is built on its own. The control panel field (supgeo) is an ES module so it can
// lazy-load the GeoJSON editor; the front-end embed bundle is a single self-contained script.
const entry = process.env.GEO_ENTRY || 'supgeo';

export default defineConfig({
  // Lazy chunks (e.g. the GeoJSON editor) load relative to the script, wherever Craft publishes it.
  base: './',
  build: {
    outDir: '../src/web/assets',
    emptyOutDir: entry === 'supgeo',
    rollupOptions: {
      input: {
        [entry]: `./src/${entry}.js`,
      },
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          const fileName = assetInfo.name || assetInfo.names?.[0] || '';
          if (fileName.endsWith('.css')) {
            return `css/${entry}[extname]`;
          }
          return 'assets/[name]-[hash][extname]';
        },
      },
    },
    minify: 'terser',
    terserOptions: {
        compress: {
         drop_console: false,
        },
        mangle: {
            keep_classnames: true,
            keep_fnames: true,
        },
    },
    sourcemap: false,
    // Third-party licences for everything bundled into this entry (shipped with the plugin).
    license: { fileName: `licenses/${entry}.md` },
  },
  optimizeDeps: {
    include: ['leaflet', '@geoman-io/leaflet-geoman-free'],
  },
});
