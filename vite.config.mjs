import { defineConfig } from 'vite';
import { resolve } from 'node:path';

export default defineConfig( {
	build: {
		assetsDir: '.',
		cssCodeSplit: false,
		emptyOutDir: true,
		lib: {
			entry: resolve( 'assets/src/admin-page.js' ),
			formats: [ 'iife' ],
			name: 'MandateAdminPage',
		},
		outDir: 'assets/dist',
		rollupOptions: {
			output: {
				assetFileNames: 'admin-page.css',
				entryFileNames: 'admin-page.js',
			},
		},
	},
} );
