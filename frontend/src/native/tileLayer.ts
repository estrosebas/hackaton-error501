import * as L from 'leaflet'
import { offlineTilesAllowed, readTile, saveTile, tileUrl } from './offlineMap'

export function localTileLayer() {
  if(!offlineTilesAllowed)return L.tileLayer(tileUrl,{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'})
  class Layer extends L.TileLayer {
    override createTile(coords:L.Coords,done:L.DoneCallback){
      const image=document.createElement('img');image.alt='';image.setAttribute('role','presentation')
      image.onload=()=>done(undefined,image);image.onerror=()=>done(new Error('Tesela no disponible offline'),image)
      let provider=2166136261;for(const character of tileUrl)provider=Math.imul(provider^character.charCodeAt(0),16777619)
      const id=`${provider>>>0}-${coords.z}-${coords.x}-${coords.y}`
      void (async()=>{
        const cached=await readTile(id)
        if(cached){image.src=cached;return}
        try{const response=await fetch(this.getTileUrl(coords));if(!response.ok)throw Error('Tile');const blob=await response.blob();await saveTile(id,blob);const url=URL.createObjectURL(blob);image.onload=()=>{URL.revokeObjectURL(url);done(undefined,image)};image.src=url}catch{done(new Error('Sin mapa descargado para esta zona'),image)}
      })()
      return image
    }
  }
  return new Layer(tileUrl,{maxZoom:19,attribution:import.meta.env.VITE_MAP_ATTRIBUTION||'&copy; OpenStreetMap contributors'}) as L.TileLayer
}
