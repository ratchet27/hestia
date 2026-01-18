import { defineConfig } from 'orval'

export default defineConfig({
  hestia: {
    input: {
      target: process.env.API_SPEC_URL || 'http://localhost:8000/api/doc.json',
    },
    output: {
      mode: 'tags-split',
      target: './src/api/generated',
      schemas: './src/api/generated/models',
      client: 'fetch',
      baseUrl: false,
      override: {
        mutator: {
          path: './src/api/client.ts',
          name: 'apiFetch',
        },
      },
    },
  },
})
