# Vue Composables Patterns

<composables_conventions>

## Composable Structure
```typescript
// composables/useFeature.ts
import { ref, computed, onMounted, onUnmounted } from 'vue'

export function useFeature(options: FeatureOptions = {}) {
    // 1. Reactive state
    const state = ref<State>(initialState)
    const isLoading = ref(false)
    const error = ref<Error | null>(null)

    // 2. Computed properties
    const derivedValue = computed(() => /* ... */)

    // 3. Methods
    const doSomething = async () => { /* ... */ }

    // 4. Lifecycle
    onMounted(() => { /* ... */ })
    onUnmounted(() => { /* ... */ })

    // 5. Return public API
    return {
        // State (readonly when appropriate)
        state: readonly(state),
        isLoading: readonly(isLoading),
        error: readonly(error),
        
        // Computed
        derivedValue,
        
        // Methods
        doSomething,
    }
}
```

## Common Composables

### useApi - Data Fetching
```typescript
import { ref, readonly } from 'vue'

interface UseApiOptions {
    immediate?: boolean
}

export function useApi<T>(
    fetcher: () => Promise<T>,
    options: UseApiOptions = {}
) {
    const data = ref<T | null>(null)
    const isLoading = ref(false)
    const error = ref<Error | null>(null)

    const execute = async () => {
        isLoading.value = true
        error.value = null

        try {
            data.value = await fetcher()
        } catch (e) {
            error.value = e instanceof Error ? e : new Error(String(e))
        } finally {
            isLoading.value = false
        }
    }

    const reset = () => {
        data.value = null
        error.value = null
        isLoading.value = false
    }

    if (options.immediate) {
        execute()
    }

    return {
        data: readonly(data),
        isLoading: readonly(isLoading),
        error: readonly(error),
        execute,
        reset,
    }
}

// Usage
const { data: users, isLoading, execute } = useApi(
    () => axios.get('/api/users').then(r => r.data),
    { immediate: true }
)
```

### useLocalStorage - Persistent State
```typescript
import { ref, watch } from 'vue'

export function useLocalStorage<T>(key: string, defaultValue: T) {
    const stored = localStorage.getItem(key)
    const data = ref<T>(stored ? JSON.parse(stored) : defaultValue)

    watch(
        data,
        (newValue) => {
            if (newValue === null || newValue === undefined) {
                localStorage.removeItem(key)
            } else {
                localStorage.setItem(key, JSON.stringify(newValue))
            }
        },
        { deep: true }
    )

    return data
}

// Usage
const theme = useLocalStorage('theme', 'light')
theme.value = 'dark' // Automatically persisted
```

### useDebounce - Debounced Values
```typescript
import { ref, watch, type Ref } from 'vue'

export function useDebounce<T>(value: Ref<T>, delay = 300): Ref<T> {
    const debounced = ref(value.value) as Ref<T>
    let timeout: ReturnType<typeof setTimeout>

    watch(value, (newValue) => {
        clearTimeout(timeout)
        timeout = setTimeout(() => {
            debounced.value = newValue
        }, delay)
    })

    return debounced
}

// Usage
const searchQuery = ref('')
const debouncedQuery = useDebounce(searchQuery, 500)

watch(debouncedQuery, (query) => {
    // Fires 500ms after user stops typing
    fetchResults(query)
})
```

### useToggle - Boolean State
```typescript
import { ref, type Ref } from 'vue'

export function useToggle(initial = false): [Ref<boolean>, () => void] {
    const state = ref(initial)
    const toggle = () => {
        state.value = !state.value
    }

    return [state, toggle]
}

// Usage
const [isOpen, toggleOpen] = useToggle(false)
```

### useEventListener - Event Handling
```typescript
import { onMounted, onUnmounted, type Ref, unref } from 'vue'

export function useEventListener<K extends keyof WindowEventMap>(
    target: Window | HTMLElement | Ref<HTMLElement | null>,
    event: K,
    handler: (e: WindowEventMap[K]) => void
) {
    onMounted(() => {
        const el = unref(target) ?? window
        el.addEventListener(event, handler as EventListener)
    })

    onUnmounted(() => {
        const el = unref(target) ?? window
        el.removeEventListener(event, handler as EventListener)
    })
}

// Usage
useEventListener(window, 'scroll', handleScroll)
useEventListener(elementRef, 'click', handleClick)
```

### useClickOutside - Click Outside Detection
```typescript
import { onMounted, onUnmounted, type Ref } from 'vue'

export function useClickOutside(
    elementRef: Ref<HTMLElement | null>,
    callback: () => void
) {
    const handler = (event: MouseEvent) => {
        const el = elementRef.value
        if (el && !el.contains(event.target as Node)) {
            callback()
        }
    }

    onMounted(() => {
        document.addEventListener('click', handler)
    })

    onUnmounted(() => {
        document.removeEventListener('click', handler)
    })
}

// Usage
const dropdownRef = ref<HTMLElement | null>(null)
useClickOutside(dropdownRef, () => {
    isOpen.value = false
})
```

### useForm - Form Handling
```typescript
import { reactive, ref, computed } from 'vue'

interface UseFormOptions<T> {
    initialValues: T
    validate?: (values: T) => Record<string, string>
    onSubmit: (values: T) => Promise<void>
}

export function useForm<T extends Record<string, any>>(options: UseFormOptions<T>) {
    const values = reactive({ ...options.initialValues }) as T
    const errors = ref<Record<string, string>>({})
    const isSubmitting = ref(false)
    const isDirty = ref(false)

    const isValid = computed(() => Object.keys(errors.value).length === 0)

    const validateForm = () => {
        if (options.validate) {
            errors.value = options.validate(values)
        }
        return isValid.value
    }

    const handleSubmit = async () => {
        if (!validateForm()) return
        
        isSubmitting.value = true
        try {
            await options.onSubmit(values)
            isDirty.value = false
        } finally {
            isSubmitting.value = false
        }
    }

    const reset = () => {
        Object.assign(values, options.initialValues)
        errors.value = {}
        isDirty.value = false
    }

    const setFieldError = (field: string, message: string) => {
        errors.value[field] = message
    }

    return {
        values,
        errors,
        isSubmitting,
        isDirty,
        isValid,
        handleSubmit,
        reset,
        setFieldError,
        validateForm,
    }
}

// Usage
const { values, errors, handleSubmit, isSubmitting } = useForm({
    initialValues: { email: '', password: '' },
    validate: (vals) => {
        const errs: Record<string, string> = {}
        if (!vals.email) errs.email = 'Email is required'
        if (!vals.password) errs.password = 'Password is required'
        return errs
    },
    onSubmit: async (vals) => {
        await api.login(vals)
    }
})
```

### useIntersectionObserver - Lazy Loading
```typescript
import { ref, onMounted, onUnmounted, type Ref } from 'vue'

export function useIntersectionObserver(
    target: Ref<HTMLElement | null>,
    options: IntersectionObserverInit = {}
) {
    const isIntersecting = ref(false)
    let observer: IntersectionObserver | null = null

    onMounted(() => {
        observer = new IntersectionObserver(([entry]) => {
            isIntersecting.value = entry.isIntersecting
        }, options)

        if (target.value) {
            observer.observe(target.value)
        }
    })

    onUnmounted(() => {
        observer?.disconnect()
    })

    return { isIntersecting }
}

// Usage - Lazy load component when visible
const sectionRef = ref<HTMLElement | null>(null)
const { isIntersecting } = useIntersectionObserver(sectionRef, {
    threshold: 0.1
})
```

## Best Practices

### Naming Convention
- Always prefix with `use`
- Name describes the feature: `useAuth`, `useCart`, `useNotifications`
- Keep names specific: `useUserProfile` not just `useUser`

### Return Values
- Return readonly refs when state shouldn't be modified externally
- Group related values in objects
- Include both state and methods

### Side Effects
- Clean up in `onUnmounted`
- Handle errors gracefully
- Provide loading states

### Reusability
- Accept configuration options
- Don't hardcode values
- Keep composables focused on one responsibility

</composables_conventions>
