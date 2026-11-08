import {
  inject,
  provide,
  ref,
  shallowRef,
  type InjectionKey,
  onScopeDispose,
  watch,
} from 'vue'
import { api } from '../services/api'
import { currentUser } from './useAuth'

export interface Comment {
  id: string
  author_id: number
  likes: number
  liked: boolean
  author: string
  avatar: string
  text: string
  time: string
}

export interface Post {
  is_demo?: boolean
  id: string
  author_id: number
  kind: string
  status: string
  owned: boolean
  tags: string[]
  categories?: string[]
  topics?: string[]
  recommended?: boolean
  trending?: boolean
  comments_count: number
  author: string
  avatar: string
  neighborhood: string
  time: string
  isWelcome: boolean
  paragraphs: string[]
  has_photo: boolean
  photo_count?: number
  activity: ActivityDetails | null
  welcomed: boolean
  reactions: number
  saved: boolean
  comments: Comment[]
}

export interface ActivityDetails {
  title: string
  starts_at: string
  location: string
  latitude?: number | null
  longitude?: number | null
}

const activityDate = new Intl.DateTimeFormat('es-PE', {
  dateStyle: 'medium',
  timeStyle: 'short',
})

export function formatActivityDate(startsAt: string) {
  return activityDate.format(new Date(startsAt))
}

export const postKinds = [
  { value: 'moment', label: 'Momento del barrio' },
  { value: 'need_help', label: 'Necesito ayuda' },
  { value: 'offer_help', label: 'Ofrezco ayuda' },
  { value: 'collaboration', label: 'Colaboración' },
  { value: 'initiative', label: 'Iniciativa vecinal' },
  { value: 'activity', label: 'Actividad comunitaria' },
  { value: 'alert', label: 'Alerta vecinal' },
  { value: 'emergency', label: 'Emergencia' },
]
function createCommunity() {
  const neighborhood = shallowRef(
    currentUser.value?.neighborhood || 'Tu comunidad',
  )
  const posts = ref<Post[]>([])
  const activities = ref<Post[]>([])
  const activitiesLoading = ref(true)
  const activitiesError = ref(false)
  let activityGeneration = 0
  const hasLoaded = ref(false)
  const loading = ref(true),
    error = ref(''),
    kind = ref(''),
    savedOnly = ref(false)
  const searchText=ref(''), categoryFilter=ref('')
  const nextCursor = ref<string | null>(null)
  const retryMore = ref(false)
  const pending = new Set<string>()
  let generation = 0
  let postsRequest: AbortController | undefined
  let activitiesRequest: AbortController | undefined
  function fail(e: unknown) {
    error.value =
      e instanceof Error ? e.message : 'No pudimos completar la solicitud.'
  }
  async function loadActivities() {
    const current = ++activityGeneration
    activitiesRequest?.abort()
    const request = new AbortController()
    activitiesRequest = request
    activitiesLoading.value = true
    activitiesError.value = false
    try {
      const response = await api<{ data: Post[] }>('/feed?kind=activity', { signal: request.signal, cache: 'no-store' })
      if (current === activityGeneration) activities.value = response.data
    } catch {
      if (current === activityGeneration) activitiesError.value = true
    } finally {
      if (current === activityGeneration) activitiesLoading.value = false
    }
  }
  async function loadPosts(more = false) {
    if (more && (loading.value || !nextCursor.value)) return
    const current = ++generation
    postsRequest?.abort()
    const request = new AbortController()
    postsRequest = request
    retryMore.value = more
    loading.value = true
    error.value = ''
    const query = new URLSearchParams()
    if(searchText.value.trim())query.set('q',searchText.value.trim())
    if(categoryFilter.value.trim())query.set('category',categoryFilter.value.trim())
    if (kind.value) query.set('kind', kind.value)
    if (savedOnly.value) query.set('saved', '1')
    if (more && nextCursor.value) query.set('cursor', nextCursor.value)
    try {
      const response = await api<{
        data: Post[]
        next_cursor: string | null
      }>(`/feed?${query}`, { signal: request.signal, cache: 'no-store' })
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
      if (current === generation) { loading.value = false; hasLoaded.value = true }
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
  async function toggleCommentLike(postId: string, commentId: string) {
    const comment = posts.value
      .find((p) => p.id === postId)
      ?.comments.find((c) => c.id === commentId)
    const key = `comment-like-${commentId}`
    if (!comment || pending.has(key)) return
    pending.add(key)
    try {
      const result = await api<{ likes: number; liked: boolean }>(
        `/feed/${postId}/comments/${commentId}/reaction`,
        { method: 'PUT', body: JSON.stringify({ selected: !comment.liked }) },
      )
      Object.assign(comment, result)
    } catch (e) {
      fail(e)
    } finally {
      pending.delete(key)
    }
  }
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
  async function addPost(
    text: string,
    type: string,
    photos: Blob[],
    activity?: ActivityDetails,
    topics: string[] = [],
  ) {
    const fields = {
      body: text,
      kind: type,
      topics,
      ...(activity && {
        activity_title: activity.title,
        activity_starts_at: activity.starts_at,
        activity_location: activity.location,
        ...(activity.latitude != null && { latitude: activity.latitude }),
        ...(activity.longitude != null && { longitude: activity.longitude }),
      }),
    }
    let body: BodyInit = JSON.stringify(fields)
    if (photos.length) {
      const form = new FormData()
      for (const [key, value] of Object.entries(fields)) {
        if (key === 'topics') { for (const topic of topics) form.append('topics[]', topic) }
        else if (value != null) form.set(key, String(value))
      }
      for (const photo of photos) {
        form.append(
          'photos[]',
          photo,
          photo.type === 'image/webp' ? 'foto.webp' : 'foto.png',
        )
      }
      body = form
    }
    await api('/feed', {
      method: 'POST',
      body,
    })
    kind.value = ''
    savedOnly.value = false
    const refreshPosts = loadPosts()
    if (type === 'activity') await loadActivities()
    await refreshPosts
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
      if (post.kind === 'activity') void loadActivities()
    } catch (e) {
      fail(e)
    }
  }
  async function remove(id: string) {
    const key = `remove:${id}`
    if (pending.has(key)) return false
    pending.add(key)
    try {
      await api(`/feed/${id}`, { method: 'DELETE' })
      posts.value = posts.value.filter((p) => p.id !== id)
      if (activities.value.some((activity) => activity.id === id)) {
        activities.value = activities.value.filter(
          (activity) => activity.id !== id,
        )
        void loadActivities()
      }
      return true
    } catch (e) {
      fail(e)
      return false
    } finally {
      pending.delete(key)
    }
  }
  watch([() => currentUser.value?.id, () => currentUser.value?.ubigeo, () => JSON.stringify(currentUser.value?.feed_interests ?? [])], ([id, ubigeo]) => {
    generation++; activityGeneration++
    postsRequest?.abort(); activitiesRequest?.abort()
    neighborhood.value = currentUser.value?.neighborhood || 'Tu comunidad'
    posts.value = []; activities.value = []; nextCursor.value = null; hasLoaded.value = false
    if (!id || !ubigeo) { loading.value = false; activitiesLoading.value = false; return }
    void loadPosts()
    void loadActivities()
  }, { immediate: true })
  onScopeDispose(() => { generation++; activityGeneration++; postsRequest?.abort(); activitiesRequest?.abort() })
  watch(() => currentUser.value?.neighborhood, value => { neighborhood.value = value || 'Tu comunidad' })
  return {
    neighborhood,
    posts,
    activities,
    activitiesLoading,
    activitiesError,
    loadActivities,
    loading,
    hasLoaded,
    error,
    kind,
    savedOnly, searchText, categoryFilter,
    nextCursor,
    retryMore,
    toggleCommentLike,
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
