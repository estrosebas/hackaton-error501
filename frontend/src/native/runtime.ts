import { App } from '@capacitor/app'
import { Network } from '@capacitor/network'
import { Capacitor } from '@capacitor/core'
import { StatusBar, Style } from '@capacitor/status-bar'
import './backgroundLocation'

export async function initializeNativeRuntime() {
  if(!Capacitor.isNativePlatform())return
  await StatusBar.setStyle({style:Style.Dark}).catch(()=>{})
  await Network.addListener('networkStatusChange',status=>window.dispatchEvent(new Event(status.connected?'online':'offline')))
  await App.addListener('appStateChange',async state=>{if(state.isActive&&(await Network.getStatus()).connected)window.dispatchEvent(new Event('online'))})
}
