import { defineConfig } from 'vite-plus';
import { preview } from 'vite-plus/test/browser-preview';

export default defineConfig({
	test: {
		include: ['tests/webcomponents/*.test.ts'],
		browser: {
			enabled: true,
			provider: preview(),
			instances: [{ browser: 'chromium' }],
		},
	},
});
