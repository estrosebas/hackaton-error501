import type { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
  appId: 'com.error501.vecino',
  appName: 'masi',
  webDir: 'dist',
  android: { useLegacyBridge: true },
  plugins: { CapacitorHttp: { enabled: true }, SplashScreen: { launchAutoHide: true } },
}

export default config
