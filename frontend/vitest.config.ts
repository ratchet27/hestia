import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'
import path from 'node:path'

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    // Cap workers: on high-core machines 16+ parallel jsdom forks make async
    // provider/i18n updates land late, tripping the strict act-warning check in
    // setup.ts (intermittent failures). min(cores, 8) — a no-op on small CI runners.
    maxWorkers: 8,
    include: ['src/**/*.{test,spec}.{ts,tsx}'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html'],
      exclude: [
        'node_modules/',
        'src/test/',
        'src/api/generated/',
        '**/*.d.ts',
      ],
    },
  },
})
