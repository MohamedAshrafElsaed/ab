# Vue 3 Patterns

<vue_conventions>

## Component Structure (Composition API)
```vue
<script setup lang="ts">
// 1. Imports
import { ref, computed, watch, onMounted } from 'vue'
import { useForm } from '@inertiajs/vue3'
import type { Project } from '@/types'

// 2. Props
interface Props {
    project: Project
    editable?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    editable: false,
})

// 3. Emits
const emit = defineEmits<{
    'update': [project: Project]
    'delete': []
}>()

// 4. Composables
const { isLoading, error, fetchData } = useApi()

// 5. Reactive State
const isOpen = ref(false)
const searchQuery = ref('')

// 6. Computed Properties
const filteredItems = computed(() => {
    return props.project.files.filter(f => 
        f.name.includes(searchQuery.value)
    )
})

// 7. Watchers
watch(searchQuery, (newVal) => {
    console.log('Search changed:', newVal)
})

// 8. Methods
const handleSubmit = () => {
    emit('update', props.project)
}

// 9. Lifecycle Hooks
onMounted(() => {
    fetchData()
})
</script>

<template>
    <!-- Template content -->
</template>

<style scoped>
/* Component-specific styles */
</style>
```

## Reactivity Patterns

### Refs vs Reactive
```typescript
// Use ref for primitives and single values
const count = ref(0)
const isLoading = ref(false)
const user = ref<User | null>(null)

// Access with .value in script
count.value++

// Use reactive for objects (optional - ref works too)
const state = reactive({
    items: [],
    filters: { search: '', status: 'all' }
})

// No .value needed
state.items.push(item)
```

### Computed Properties
```typescript
// Simple computed
const fullName = computed(() => `${firstName.value} ${lastName.value}`)

// Writable computed
const selected = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
})
```

### Watchers
```typescript
// Watch single ref
watch(searchQuery, (newVal, oldVal) => {
    console.log(`Changed from ${oldVal} to ${newVal}`)
})

// Watch multiple sources
watch([firstName, lastName], ([newFirst, newLast]) => {
    fullName.value = `${newFirst} ${newLast}`
})

// Deep watch
watch(state, (newState) => {
    console.log('State changed:', newState)
}, { deep: true })

// Immediate execution
watch(userId, async (id) => {
    user.value = await fetchUser(id)
}, { immediate: true })

// watchEffect - auto-tracks dependencies
watchEffect(() => {
    console.log('Count is:', count.value)
})
```

## Props & Emits

### TypeScript Props
```typescript
// With interface
interface Props {
    title: string
    items: Item[]
    selected?: Item | null
    loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    selected: null,
    loading: false,
})

// Runtime validation (alternative)
const props = defineProps({
    title: { type: String, required: true },
    items: { type: Array as PropType<Item[]>, required: true },
    selected: { type: Object as PropType<Item | null>, default: null },
})
```

### TypeScript Emits
```typescript
const emit = defineEmits<{
    'update:modelValue': [value: string]
    'submit': [data: FormData]
    'close': []
}>()

// Emit events
emit('update:modelValue', newValue)
emit('submit', formData)
emit('close')
```

## v-model Patterns

### Custom v-model
```vue
<script setup>
const props = defineProps<{ modelValue: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const value = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
})
</script>

<template>
    <input v-model="value" />
</template>
```

### Multiple v-models
```vue
<!-- Parent -->
<UserForm v-model:firstName="first" v-model:lastName="last" />

<!-- Child -->
<script setup>
defineProps<{ firstName: string; lastName: string }>()
defineEmits<{
    'update:firstName': [value: string]
    'update:lastName': [value: string]
}>()
</script>
```

## Template Patterns

### Conditional Rendering
```vue
<template>
    <!-- v-if for conditional creation/destruction -->
    <Modal v-if="isOpen" @close="isOpen = false" />
    
    <!-- v-show for frequent toggles (keeps in DOM) -->
    <Tooltip v-show="isHovered" :text="tooltip" />
    
    <!-- v-if / v-else-if / v-else -->
    <LoadingSpinner v-if="loading" />
    <ErrorMessage v-else-if="error" :error="error" />
    <DataTable v-else :items="items" />
</template>
```

### List Rendering
```vue
<template>
    <ul>
        <li v-for="item in items" :key="item.id">
            {{ item.name }}
        </li>
    </ul>
    
    <!-- With index -->
    <div v-for="(item, index) in items" :key="item.id">
        {{ index + 1 }}. {{ item.name }}
    </div>
    
    <!-- Object iteration -->
    <div v-for="(value, key) in object" :key="key">
        {{ key }}: {{ value }}
    </div>
</template>
```

### Slots
```vue
<!-- Parent using slots -->
<Card>
    <template #header>
        <h2>Title</h2>
    </template>
    
    <template #default>
        <p>Content goes here</p>
    </template>
    
    <template #footer="{ close }">
        <button @click="close">Close</button>
    </template>
</Card>

<!-- Child defining slots -->
<template>
    <div class="card">
        <header><slot name="header" /></header>
        <main><slot /></main>
        <footer><slot name="footer" :close="handleClose" /></footer>
    </div>
</template>
```

## Best Practices

### Performance
```typescript
// Use shallowRef for large objects that replace entirely
const largeData = shallowRef<BigObject>(null)

// Use v-once for static content
<span v-once>{{ staticText }}</span>

// Use v-memo for expensive list items
<div v-for="item in list" :key="item.id" v-memo="[item.id, item.updated]">
    <ExpensiveComponent :item="item" />
</div>
```

### Organization
- One component per file
- Use PascalCase for component names
- Colocate related files (Component.vue, Component.test.ts)
- Extract reusable logic into composables
- Keep components focused and small (<200 lines)

</vue_conventions>
