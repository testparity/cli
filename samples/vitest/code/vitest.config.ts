import { defineConfig } from 'vitest/config'

export default defineConfig({
  test: {
    coverage: {
      include: ['src/**/*.ts'],
      reportsDirectory: 'coverage',
      reporter: ['clover', 'text'],
    },
  },
})
