import {
  computed,
  inject,
  provide,
  ref,
  shallowRef,
  type InjectionKey,
} from 'vue'

export interface Comment {
  id: string
  author: string
  avatar: string
  text: string
  time: string
}

export interface Post {
  id: string
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

function createCommunity() {
  const neighborhood = shallowRef('San Blas')
  const posts = ref<Post[]>([
    {
      id: 'welcome-miguel',
      author: 'Miguel',
      avatar: '/images/miguel.jpg',
      neighborhood: 'San Blas',
      time: 'Hace 2 h',
      isWelcome: true,
      paragraphs: [
        '¡Hola, vecinos!',
        'Mi esposa y yo acabamos de mudarnos a San Blas. Estamos arreglando nuestra casita y nos encantaría conocerlos. Si nos ven por el barrio, ¡pasen a saludar!',
      ],
      welcomed: true,
      reactions: 7,
      saved: false,
      comments: [
        {
          id: 'lucia-welcome',
          author: 'Lucía',
          avatar: '/images/lucia.jpg',
          text: '¡Bienvenidos al barrio, Miguel! Qué lindo tenerlos por aquí.',
          time: 'Hace 1 h',
        },
      ],
    },
  ])
  const visiblePosts = computed(() =>
    posts.value.filter((post) => post.neighborhood === neighborhood.value),
  )

  function toggleWelcome(id: string) {
    const post = posts.value.find((item) => item.id === id)
    if (!post) return
    post.welcomed = !post.welcomed
    post.reactions += post.welcomed ? 1 : -1
  }

  function addComment(id: string, text: string) {
    const post = posts.value.find((item) => item.id === id)
    if (!post || !text.trim()) return
    post.comments.push({
      id: crypto.randomUUID(),
      author: 'Tú',
      avatar: '/images/ana.jpg',
      text: text.trim(),
      time: 'Ahora',
    })
  }

  function addPost(text: string) {
    if (!text.trim()) return
    posts.value.unshift({
      id: crypto.randomUUID(),
      author: 'Tú',
      avatar: '/images/ana.jpg',
      neighborhood: neighborhood.value,
      time: 'Ahora',
      isWelcome: false,
      paragraphs: text.trim().split('\n').filter(Boolean),
      welcomed: false,
      reactions: 0,
      saved: false,
      comments: [],
    })
  }

  return {
    neighborhood,
    posts,
    visiblePosts,
    toggleWelcome,
    addComment,
    addPost,
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
