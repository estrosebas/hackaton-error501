import {
  computed,
  inject,
  provide,
  ref,
  shallowRef,
  type InjectionKey,
  onMounted,
} from 'vue'
import { api } from '../services/api'
import { currentUser } from './useAuth'

export interface Comment {
  id: string
  author: string
  avatar: string
  text: string
  time: string
}

export interface Post {
  id: string
  kind: string
  status: string
  owned: boolean
  tags: string[]
  comments_count: number
  author: string
  avatar: string
  neighborhood: string
  time: string
  isWelcome: boolean
  paragraphs: string[]
  welcomed: boolean
  reactions: number
  saved: boolean
  comments: Comment[]
}

export interface Activity {
  id: string
  title: string
  category: string
  date: string
  time: string
  location: string
  image?: string
  description: string
}

export const activities: Activity[] = [
  {
    id: 'garden',
    title: 'Un huerto entre todos',
    category: 'Comunidad',
    date: 'SÁB',
    time: '09:00',
    location: 'Huerto de San Blas',
    image: '/images/garden.jpg',
    description:
      'Trae tus ganas de aprender. Compartiremos semillas, prepararemos la tierra y cuidaremos juntos nuestro huerto. Puedes venir con toda la familia.',
  },
  {
    id: 'market',
    title: 'Hecho en el barrio',
    category: 'Feria local',
    date: 'DOM',
    time: '10:00',
    location: 'Plazoleta de San Blas',
    description:
      'Una mañana para conocer a nuestros artesanos, descubrir pequeños emprendimientos y apoyar lo que nace cerquita de casa.',
  },
  {
    id: 'walk',
    title: 'Caminemos juntos',
    category: 'Al aire libre',
    date: 'DOM',
    time: '08:00',
    location: 'Salida desde la plazoleta',
    image: '/images/walk.jpg',
    description:
      'Una caminata tranquila para conocer los miradores del barrio y conversar con tus vecinos. Lleva agua, un sombrero y zapatos cómodos.',
  },
]

export const postKinds = [
  { value: 'need_help', label: 'Necesito ayuda' },
  { value: 'offer_help', label: 'Ofrezco ayuda' },
  { value: 'collaboration', label: 'Colaboración' },
  { value: 'initiative', label: 'Iniciativa vecinal' },
  { value: 'activity', label: 'Actividad comunitaria' },
  { value: 'alert', label: 'Alerta vecinal' },
]
function createCommunity() {
  const neighborhood = shallowRef(
    currentUser.value?.neighborhood || 'Tu comunidad',
  )
  const posts = ref<Post[]>([])
  const visiblePosts = computed(() => posts.value)
  const loading = ref(false),
    error = ref(''),
    kind = ref(''),
    savedOnly = ref(false)
  const nextCursor = ref<string | null>(null)
  const pending = new Set<string>()
  let generation = 0
  function fail(e: unknown) {
    error.value =
      e instanceof Error ? e.message : 'No pudimos completar la solicitud.'
  }
  async function loadPosts(more = false) {
    if (more && (loading.value || !nextCursor.value)) return
    const current = ++generation
    loading.value = true
    error.value = ''
    const query = new URLSearchParams()
    if (kind.value) query.set('kind', kind.value)
    if (savedOnly.value) query.set('saved', '1')
    if (more && nextCursor.value) query.set('cursor', nextCursor.value)
    try {
      const response = await api<{
        data: Post[]
        next_cursor: string | null
      }>(`/feed?${query}`)
      if (current !== generation) return
      posts.value = more
        ? [
            ...posts.value,
            ...response.data.filter(
              (p) => !posts.value.some((old) => old.id === p.id),
            ),
          ]
        : response.data
      nextCursor.value = response.next_cursor
    } catch (e) {
      if (current === generation) fail(e)
    } finally {
      if (current === generation) loading.value = false
    }
  }
  async function loadComments(id: string) {
    const post = posts.value.find((p) => p.id === id)
    if (!post) return
    try {
      const response = await api<{
        data: Comment[]
        next_cursor: string | null
      }>(`/feed/${id}/comments`)
      post.comments = response.data.slice().reverse()
      commentCursors.value[id] = response.next_cursor
    } catch (e) {
      fail(e)
    }
  }
  const commentCursors = ref<Record<string, string | null>>({})
  async function moreComments(id: string) {
    const cursor = commentCursors.value[id],
      post = posts.value.find((p) => p.id === id)
    if (!cursor || !post || pending.has('comments' + id)) return
    pending.add('comments' + id)
    try {
      const response = await api<{
        data: Comment[]
        next_cursor: string | null
      }>(`/feed/${id}/comments?cursor=${encodeURIComponent(cursor)}`)
      post.comments = [...response.data.reverse(), ...post.comments]
      commentCursors.value[id] = response.next_cursor
    } catch (e) {
      fail(e)
    } finally {
      pending.delete('comments' + id)
    }
  }
  async function selection(id: string, action: 'reaction' | 'bookmark') {
    const post = posts.value.find((p) => p.id === id),
      key = action + id
    if (!post || pending.has(key)) return
    pending.add(key)
    error.value = ''
    try {
      const response = await api<{ data: Post }>(`/feed/${id}/${action}`, {
        method: 'PUT',
        body: JSON.stringify({
          selected: action === 'reaction' ? !post.welcomed : !post.saved,
        }),
      })
      Object.assign(post, { ...response.data, comments: post.comments })
    } catch (e) {
      fail(e)
    } finally {
      pending.delete(key)
    }
  }
  async function addComment(
    id: string,
    text: string,
    done?: (success: boolean) => void,
  ) {
    try {
      await api(`/feed/${id}/comments`, {
        method: 'POST',
        body: JSON.stringify({ body: text }),
      })
      await loadComments(id)
      const p = posts.value.find((p) => p.id === id)
      if (p) p.comments_count++
      done?.(true)
    } catch (e) {
      fail(e)
      done?.(false)
    }
  }
  async function addPost(text: string, type: string) {
    await api('/feed', {
      method: 'POST',
      body: JSON.stringify({ body: text, kind: type }),
    })
    kind.value = ''
    savedOnly.value = false
    await loadPosts()
  }
  async function resolve(id: string) {
    const post = posts.value.find((p) => p.id === id)
    if (!post) return
    try {
      const response = await api<{ data: Post }>(`/feed/${id}`, {
        method: 'PATCH',
        body: JSON.stringify({
          status: post.status === 'open' ? 'resolved' : 'open',
        }),
      })
      Object.assign(post, { ...response.data, comments: post.comments })
    } catch (e) {
      fail(e)
    }
  }
  async function remove(id: string) {
    try {
      await api(`/feed/${id}`, { method: 'DELETE' })
      posts.value = posts.value.filter((p) => p.id !== id)
    } catch (e) {
      fail(e)
    }
  }
  onMounted(() => {
    void loadPosts()
  })
  return {
    neighborhood,
    posts,
    visiblePosts,
    loading,
    error,
    kind,
    savedOnly,
    nextCursor,
    loadPosts,
    loadComments,
    moreComments,
    commentCursors,
    toggleWelcome: (id: string) => selection(id, 'reaction'),
    toggleSave: (id: string) => selection(id, 'bookmark'),
    addComment,
    addPost,
    resolve,
    remove,
  }
}

const communityKey: InjectionKey<ReturnType<typeof createCommunity>> =
  Symbol('community')

export function provideCommunity() {
  const community = createCommunity()
  provide(communityKey, community)
  return community
}

export function useCommunity() {
  const community = inject(communityKey)
  if (!community) throw new Error('Community provider is missing')
  return community
}
