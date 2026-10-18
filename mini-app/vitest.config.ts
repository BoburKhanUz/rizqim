import { defineConfig, mergeConfig } from 'vitest/config'
import viteConfig from './vite.config.ts'

export default mergeConfig(
  viteConfig,
  defineConfig({
    test: {
      environment: 'jsdom',
      setupFiles: ['./src/test/setup.ts'],
      include: ['src/**/*.test.{ts,tsx}'],
      css: false,
      // Every test starts from clean mocks, spies and fake timers
      restoreMocks: true,
      unstubGlobals: true,
      unstubEnvs: true,
    },
  }),
)
