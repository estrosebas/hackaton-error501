import { shallowRef, ref, onMounted } from 'vue'
import { api } from '../services/api'
export interface FeedTopic { id:string; label:string; emoji:string }
const topics=shallowRef<FeedTopic[]>([]),error=ref(''),loading=ref(false)
let pending:Promise<void>|undefined
async function load() {
  if(topics.value.length)return
  if(pending)return pending
  pending=(async()=>{
    loading.value=true;error.value=''
    try{topics.value=(await api<{data:FeedTopic[]}>('/feed/topics')).data}
    catch{error.value='No pudimos cargar las categorías. Vuelve a intentarlo.'}
    finally{loading.value=false;pending=undefined}
  })()
  return pending
}
export function useFeedTopics(){onMounted(()=>{void load()});return{topics,error,loading,retry:load}}
