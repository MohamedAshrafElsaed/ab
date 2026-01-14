# UI Component Task Template

<task_context>
You are building or modifying UI components for a {{FRAMEWORK}} application.

<project_info>
{{PROJECT_INFO}}
</project_info>

<tech_stack>
{{TECH_STACK}}
</tech_stack>

<relevant_code>
{{RELEVANT_FILES}}
</relevant_code>
</task_context>

<user_request>
{{USER_REQUEST}}
</user_request>

<instructions>
Create a UI component following these guidelines:

1. **Component Analysis**
    - What is the component's purpose?
    - What props/inputs does it need?
    - What events/outputs should it emit?
    - What states does it need to manage?

2. **Design Considerations**
    - Follow existing design patterns in the project
    - Ensure accessibility (ARIA, keyboard navigation)
    - Make it responsive
    - Support loading and error states

3. **Implementation**
    - Use Composition API for Vue components
    - Follow project's styling conventions (Tailwind, etc.)
    - Extract reusable logic into composables
    - Type all props and emits properly

4. **Integration**
    - How does it connect to backend data?
    - What API calls does it need?
    - How does it handle form submission?
      </instructions>

<component_patterns>
<vue3_composition>
```vue
<script setup lang="ts">
import { ref, computed } from 'vue'

interface Props {
    modelValue: string
    disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
})

const emit = defineEmits<{
    'update:modelValue': [value: string]
    'submit': []
}>()

// Component logic here
</script>

<template>
    <!-- Template here -->
</template>
```
</vue3_composition>

<inertia_form>
```vue
<script setup>
import { useForm } from '@inertiajs/vue3'

const form = useForm({
    name: '',
    email: '',
})

const submit = () => {
    form.post(route('users.store'), {
        onSuccess: () => form.reset(),
    })
}
</script>
```
</inertia_form>
</component_patterns>

<examples>
<example>
<request>Create a searchable dropdown component</request>
<analysis>
Component requirements:
- Text input for searching
- Dropdown list of filtered options
- Keyboard navigation support
- v-model binding for selected value
- Empty state when no matches
</analysis>
<implementation>
<file path="resources/js/Components/SearchableDropdown.vue" action="create">
```vue
<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'

interface Option {
value: string | number
label: string
}

interface Props {
modelValue: string | number | null
options: Option[]
placeholder?: string
disabled?: boolean
searchPlaceholder?: string
}

const props = withDefaults(defineProps<Props>(), {
placeholder: 'Select an option',
disabled: false,
searchPlaceholder: 'Search...',
})

const emit = defineEmits<{
'update:modelValue': [value: string | number | null]
'search': [query: string]
}>()

const isOpen = ref(false)
const searchQuery = ref('')
const highlightedIndex = ref(0)
const dropdownRef = ref<HTMLElement | null>(null)
const inputRef = ref<HTMLInputElement | null>(null)

const filteredOptions = computed(() => {
if (!searchQuery.value) return props.options
const query = searchQuery.value.toLowerCase()
return props.options.filter(opt =>
opt.label.toLowerCase().includes(query)
)
})

const selectedLabel = computed(() => {
const selected = props.options.find(opt => opt.value === props.modelValue)
return selected?.label ?? props.placeholder
})

const open = () => {
if (props.disabled) return
isOpen.value = true
highlightedIndex.value = 0
setTimeout(() => inputRef.value?.focus(), 0)
}

const close = () => {
isOpen.value = false
searchQuery.value = ''
}

const select = (option: Option) => {
emit('update:modelValue', option.value)
close()
}

const handleKeydown = (e: KeyboardEvent) => {
if (!isOpen.value) return

    switch (e.key) {
        case 'ArrowDown':
            e.preventDefault()
            highlightedIndex.value = Math.min(
                highlightedIndex.value + 1,
                filteredOptions.value.length - 1
            )
            break
        case 'ArrowUp':
            e.preventDefault()
            highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0)
            break
        case 'Enter':
            e.preventDefault()
            if (filteredOptions.value[highlightedIndex.value]) {
                select(filteredOptions.value[highlightedIndex.value])
            }
            break
        case 'Escape':
            close()
            break
    }
}

const handleClickOutside = (e: MouseEvent) => {
if (dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) {
close()
}
}

watch(searchQuery, (query) => {
emit('search', query)
highlightedIndex.value = 0
})

onMounted(() => {
document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div ref="dropdownRef" class="relative">
        <button
            type="button"
            :disabled="disabled"
            class="w-full px-4 py-2 text-left bg-white border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            @click="open"
        >
            <span :class="{ 'text-gray-400': !modelValue }">
                {{ selectedLabel }}
            </span>
            <svg
                class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div
            v-if="isOpen"
            class="absolute z-50 w-full mt-1 bg-white border rounded-lg shadow-lg"
        >
            <div class="p-2 border-b">
                <input
                    ref="inputRef"
                    v-model="searchQuery"
                    type="text"
                    :placeholder="searchPlaceholder"
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                    @keydown="handleKeydown"
                />
            </div>

            <ul class="max-h-60 overflow-auto py-1" role="listbox">
                <li
                    v-for="(option, index) in filteredOptions"
                    :key="option.value"
                    role="option"
                    :aria-selected="option.value === modelValue"
                    :class="[
                        'px-4 py-2 cursor-pointer',
                        index === highlightedIndex ? 'bg-blue-50' : '',
                        option.value === modelValue ? 'bg-blue-100 font-medium' : '',
                    ]"
                    @click="select(option)"
                    @mouseenter="highlightedIndex = index"
                >
                    {{ option.label }}
                </li>
                
                <li v-if="filteredOptions.length === 0" class="px-4 py-2 text-gray-500 text-center">
                    No results found
                </li>
            </ul>
        </div>
    </div>
</template>
```
</file>
</implementation>
</example>

<example>
<request>Create a file upload component with drag and drop</request>
<analysis>
Component requirements:
- Drag and drop zone
- Click to browse files
- File type validation
- Size limit validation
- Upload progress indicator
- Preview for images
</analysis>
<implementation>
<file path="resources/js/Components/FileUpload.vue" action="create">
```vue
<script setup lang="ts">
import { ref, computed } from 'vue'

interface Props {
accept?: string
maxSize?: number // in MB
multiple?: boolean
}

interface UploadedFile {
file: File
preview: string | null
progress: number
error: string | null
}

const props = withDefaults(defineProps<Props>(), {
accept: '*/*',
maxSize: 10,
multiple: false,
})

const emit = defineEmits<{
'upload': [files: File[]]
'remove': [index: number]
'error': [message: string]
}>()

const isDragging = ref(false)
const files = ref<UploadedFile[]>([])
const inputRef = ref<HTMLInputElement | null>(null)

const maxSizeBytes = computed(() => props.maxSize * 1024 * 1024)

const validateFile = (file: File): string | null => {
if (file.size > maxSizeBytes.value) {
return `File size exceeds ${props.maxSize}MB limit`
}

    if (props.accept !== '*/*') {
        const acceptedTypes = props.accept.split(',').map(t => t.trim())
        const isValid = acceptedTypes.some(type => {
            if (type.startsWith('.')) {
                return file.name.toLowerCase().endsWith(type.toLowerCase())
            }
            if (type.endsWith('/*')) {
                return file.type.startsWith(type.replace('/*', '/'))
            }
            return file.type === type
        })
        
        if (!isValid) {
            return `File type not accepted. Allowed: ${props.accept}`
        }
    }
    
    return null
}

const createPreview = (file: File): Promise<string | null> => {
return new Promise((resolve) => {
if (!file.type.startsWith('image/')) {
resolve(null)
return
}

        const reader = new FileReader()
        reader.onload = (e) => resolve(e.target?.result as string)
        reader.onerror = () => resolve(null)
        reader.readAsDataURL(file)
    })
}

const addFiles = async (fileList: FileList) => {
const newFiles: File[] = []

    for (const file of Array.from(fileList)) {
        const error = validateFile(file)
        if (error) {
            emit('error', `${file.name}: ${error}`)
            continue
        }
        
        const preview = await createPreview(file)
        files.value.push({
            file,
            preview,
            progress: 0,
            error: null,
        })
        newFiles.push(file)
    }
    
    if (newFiles.length > 0) {
        emit('upload', newFiles)
    }
}

const handleDrop = (e: DragEvent) => {
isDragging.value = false
if (e.dataTransfer?.files) {
addFiles(e.dataTransfer.files)
}
}

const handleFileSelect = (e: Event) => {
const input = e.target as HTMLInputElement
if (input.files) {
addFiles(input.files)
}
input.value = ''
}

const removeFile = (index: number) => {
files.value.splice(index, 1)
emit('remove', index)
}

const openFileBrowser = () => {
inputRef.value?.click()
}

const formatSize = (bytes: number): string => {
if (bytes < 1024) return `${bytes} B`
if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
</script>

<template>
    <div class="space-y-4">
        <div
            :class="[
                'border-2 border-dashed rounded-lg p-8 text-center transition-colors cursor-pointer',
                isDragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-gray-400',
            ]"
            @dragenter.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @dragover.prevent
            @drop.prevent="handleDrop"
            @click="openFileBrowser"
        >
            <input
                ref="inputRef"
                type="file"
                :accept="accept"
                :multiple="multiple"
                class="hidden"
                @change="handleFileSelect"
            />

            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            
            <p class="mt-2 text-sm text-gray-600">
                <span class="font-medium text-blue-600">Click to upload</span>
                or drag and drop
            </p>
            <p class="mt-1 text-xs text-gray-500">
                {{ accept === '*/*' ? 'Any file type' : accept }} up to {{ maxSize }}MB
            </p>
        </div>

        <ul v-if="files.length > 0" class="space-y-2">
            <li
                v-for="(item, index) in files"
                :key="index"
                class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg"
            >
                <img
                    v-if="item.preview"
                    :src="item.preview"
                    class="w-12 h-12 object-cover rounded"
                    alt="Preview"
                />
                <div v-else class="w-12 h-12 bg-gray-200 rounded flex items-center justify-center">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{{ item.file.name }}</p>
                    <p class="text-xs text-gray-500">{{ formatSize(item.file.size) }}</p>
                </div>
                
                <button
                    type="button"
                    class="p-1 text-gray-400 hover:text-red-500"
                    @click.stop="removeFile(index)"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </li>
        </ul>
    </div>
</template>
```
</file>
</implementation>
</example>

<example>
<request>Create a toast notification system</request>
<analysis>
System requirements:
- Global toast state management
- Multiple toast types (success, error, warning, info)
- Auto-dismiss with configurable duration
- Manual dismiss option
- Stacked display with animations
</analysis>
<implementation>
<file path="resources/js/composables/useToast.ts" action="create">
```typescript
import { ref, readonly } from 'vue'

export type ToastType = 'success' | 'error' | 'warning' | 'info'

export interface Toast {
id: string
type: ToastType
message: string
duration: number
}

const toasts = ref<Toast[]>([])

export function useToast() {
const show = (message: string, type: ToastType = 'info', duration = 5000) => {
const id = `toast-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

        toasts.value.push({ id, type, message, duration })
        
        if (duration > 0) {
            setTimeout(() => dismiss(id), duration)
        }
        
        return id
    }
    
    const dismiss = (id: string) => {
        const index = toasts.value.findIndex(t => t.id === id)
        if (index > -1) {
            toasts.value.splice(index, 1)
        }
    }
    
    const success = (message: string, duration?: number) => show(message, 'success', duration)
    const error = (message: string, duration?: number) => show(message, 'error', duration)
    const warning = (message: string, duration?: number) => show(message, 'warning', duration)
    const info = (message: string, duration?: number) => show(message, 'info', duration)
    
    return {
        toasts: readonly(toasts),
        show,
        dismiss,
        success,
        error,
        warning,
        info,
    }
}
```
</file>

<file path="resources/js/Components/ToastContainer.vue" action="create">
```vue
<script setup lang="ts">
import { useToast } from '@/composables/useToast'

const { toasts, dismiss } = useToast()

const icons = {
    success: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    error: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
    warning: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    info: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
}

const colors = {
    success: 'bg-green-50 text-green-800 border-green-200',
    error: 'bg-red-50 text-red-800 border-red-200',
    warning: 'bg-yellow-50 text-yellow-800 border-yellow-200',
    info: 'bg-blue-50 text-blue-800 border-blue-200',
}

const iconColors = {
    success: 'text-green-500',
    error: 'text-red-500',
    warning: 'text-yellow-500',
    info: 'text-blue-500',
}
</script>

<template>
    <Teleport to="body">
        <div class="fixed top-4 right-4 z-50 space-y-2 w-80">
            <TransitionGroup
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="transform translate-x-full opacity-0"
                enter-to-class="transform translate-x-0 opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="transform translate-x-0 opacity-100"
                leave-to-class="transform translate-x-full opacity-0"
            >
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    :class="[colors[toast.type], 'p-4 rounded-lg shadow-lg border flex items-start gap-3']"
                >
                    <svg :class="[iconColors[toast.type], 'w-5 h-5 flex-shrink-0 mt-0.5']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="icons[toast.type]" />
                    </svg>
                    
                    <p class="flex-1 text-sm">{{ toast.message }}</p>
                    
                    <button
                        class="text-current opacity-50 hover:opacity-100"
                        @click="dismiss(toast.id)"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
```
</file>
</implementation>
</example>
</examples>

{{OUTPUT_FORMAT}}
