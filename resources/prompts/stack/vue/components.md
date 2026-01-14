# Vue Component Patterns

<component_patterns>

## Form Components

### Input with Validation
```vue
<script setup lang="ts">
import { computed } from 'vue'

interface Props {
    modelValue: string
    label: string
    error?: string
    type?: 'text' | 'email' | 'password'
    required?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    type: 'text',
    required: false,
})

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

const value = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
})

const inputId = computed(() => `input-${props.label.toLowerCase().replace(/\s+/g, '-')}`)
</script>

<template>
    <div class="space-y-1">
        <label :for="inputId" class="block text-sm font-medium text-gray-700">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </label>
        <input
            :id="inputId"
            v-model="value"
            :type="type"
            :required="required"
            :class="[
                'w-full rounded-md border px-3 py-2 shadow-sm focus:outline-none focus:ring-2',
                error
                    ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                    : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500'
            ]"
        />
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
    </div>
</template>
```

### Select Component
```vue
<script setup lang="ts">
import { computed } from 'vue'

interface Option {
    value: string | number
    label: string
    disabled?: boolean
}

interface Props {
    modelValue: string | number | null
    options: Option[]
    placeholder?: string
    disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Select an option',
    disabled: false,
})

const emit = defineEmits<{
    'update:modelValue': [value: string | number | null]
}>()

const selected = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
})
</script>

<template>
    <select
        v-model="selected"
        :disabled="disabled"
        class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100"
    >
        <option :value="null" disabled>{{ placeholder }}</option>
        <option
            v-for="option in options"
            :key="option.value"
            :value="option.value"
            :disabled="option.disabled"
        >
            {{ option.label }}
        </option>
    </select>
</template>
```

## Data Display Components

### Data Table
```vue
<script setup lang="ts">
import { computed } from 'vue'

interface Column {
    key: string
    label: string
    sortable?: boolean
    class?: string
}

interface Props {
    columns: Column[]
    data: Record<string, any>[]
    sortBy?: string
    sortDirection?: 'asc' | 'desc'
}

const props = withDefaults(defineProps<Props>(), {
    sortDirection: 'asc',
})

const emit = defineEmits<{
    'sort': [column: string]
    'row-click': [row: Record<string, any>]
}>()

const handleSort = (column: Column) => {
    if (column.sortable) {
        emit('sort', column.key)
    }
}

const sortedData = computed(() => {
    if (!props.sortBy) return props.data
    
    return [...props.data].sort((a, b) => {
        const aVal = a[props.sortBy!]
        const bVal = b[props.sortBy!]
        const modifier = props.sortDirection === 'asc' ? 1 : -1
        
        if (aVal < bVal) return -1 * modifier
        if (aVal > bVal) return 1 * modifier
        return 0
    })
})
</script>

<template>
    <div class="overflow-x-auto rounded-lg border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        :class="[
                            'px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500',
                            column.sortable && 'cursor-pointer hover:bg-gray-100',
                            column.class
                        ]"
                        @click="handleSort(column)"
                    >
                        <div class="flex items-center gap-1">
                            {{ column.label }}
                            <template v-if="column.sortable && sortBy === column.key">
                                <svg v-if="sortDirection === 'asc'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                </svg>
                                <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </template>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                <tr
                    v-for="(row, index) in sortedData"
                    :key="index"
                    class="hover:bg-gray-50 cursor-pointer"
                    @click="emit('row-click', row)"
                >
                    <td
                        v-for="column in columns"
                        :key="column.key"
                        :class="['px-6 py-4 whitespace-nowrap text-sm text-gray-900', column.class]"
                    >
                        <slot :name="`cell-${column.key}`" :value="row[column.key]" :row="row">
                            {{ row[column.key] }}
                        </slot>
                    </td>
                </tr>
                <tr v-if="sortedData.length === 0">
                    <td :colspan="columns.length" class="px-6 py-8 text-center text-gray-500">
                        <slot name="empty">No data available</slot>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
```

## Feedback Components

### Modal
```vue
<script setup lang="ts">
import { watch, onMounted, onUnmounted } from 'vue'

interface Props {
    open: boolean
    title?: string
    size?: 'sm' | 'md' | 'lg' | 'xl'
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
})

const emit = defineEmits<{
    'close': []
}>()

const sizeClasses = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
}

const handleEscape = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && props.open) {
        emit('close')
    }
}

watch(() => props.open, (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : ''
})

onMounted(() => {
    document.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
    document.removeEventListener('keydown', handleEscape)
    document.body.style.overflow = ''
})
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-50 overflow-y-auto">
                <!-- Backdrop -->
                <div
                    class="fixed inset-0 bg-black/50"
                    @click="emit('close')"
                />
                
                <!-- Modal -->
                <div class="flex min-h-full items-center justify-center p-4">
                    <div
                        :class="[
                            'relative w-full rounded-lg bg-white shadow-xl',
                            sizeClasses[size]
                        ]"
                    >
                        <!-- Header -->
                        <div v-if="title || $slots.header" class="flex items-center justify-between border-b px-6 py-4">
                            <slot name="header">
                                <h3 class="text-lg font-semibold">{{ title }}</h3>
                            </slot>
                            <button
                                class="text-gray-400 hover:text-gray-600"
                                @click="emit('close')"
                            >
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Content -->
                        <div class="px-6 py-4">
                            <slot />
                        </div>
                        
                        <!-- Footer -->
                        <div v-if="$slots.footer" class="border-t px-6 py-4">
                            <slot name="footer" />
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
```

### Loading States
```vue
<script setup lang="ts">
interface Props {
    loading: boolean
    skeleton?: boolean
}

withDefaults(defineProps<Props>(), {
    skeleton: false,
})
</script>

<template>
    <div class="relative">
        <!-- Skeleton loading -->
        <template v-if="skeleton && loading">
            <slot name="skeleton">
                <div class="animate-pulse space-y-3">
                    <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                    <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                </div>
            </slot>
        </template>
        
        <!-- Overlay loading -->
        <template v-else>
            <slot />
            <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition-opacity duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="loading"
                    class="absolute inset-0 flex items-center justify-center bg-white/75"
                >
                    <svg class="h-8 w-8 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                </div>
            </Transition>
        </template>
    </div>
</template>
```

</component_patterns>
