import { defineConfig } from 'vite';
import { resolve } from 'node:path';

export default defineConfig( {
	base: './',
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
				assetFileNames: ( assetInfo ) => assetInfo.name && assetInfo.name.endsWith( '.css' )
					? 'admin-page.css'
					: '[name][extname]',
				entryFileNames: 'admin-page.js',
			},
		},
	},
} );
