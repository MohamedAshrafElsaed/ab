<script setup lang="ts">
/**
 * Toast Notification Component
 *
 * A reusable toast notification for showing success, error, warning, and info messages.
 * Designed to match the dark theme of the application.
 *
 * Usage:
 *   <Toast
 *       v-if="showToast"
 *       type="success"
 *       message="Welcome back!"
 *       @close="showToast = false"
 *   />
 *
 * Or use the composable (useToast) for programmatic toasts.
 */

import { computed, onMounted, ref } from 'vue';

type ToastType = 'success' | 'error' | 'warning' | 'info';

interface Props {
    type?: ToastType;
    message: string;
    title?: string;
    duration?: number; // Auto-dismiss duration in ms (0 = no auto-dismiss)
    dismissible?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    type: 'info',
    duration: 5000,
    dismissible: true,
});

const emit = defineEmits<{
    close: [];
}>();

// Animation state
const isVisible = ref(false);

// Auto-dismiss timer
let dismissTimer: ReturnType<typeof setTimeout> | null = null;

/**
 * Toast styling based on type
 */
const toastStyles = computed(() => {
    const styles = {
        success: {
            bg: 'bg-[#22C55E]/10',
            border: 'border-[#22C55E]/30',
            icon: 'text-[#22C55E]',
            iconBg: 'bg-[#22C55E]/20',
        },
        error: {
            bg: 'bg-[#EF4444]/10',
            border: 'border-[#EF4444]/30',
            icon: 'text-[#EF4444]',
            iconBg: 'bg-[#EF4444]/20',
        },
        warning: {
            bg: 'bg-[#F59E0B]/10',
            border: 'border-[#F59E0B]/30',
            icon: 'text-[#F59E0B]',
            iconBg: 'bg-[#F59E0B]/20',
        },
        info: {
            bg: 'bg-[#3B82F6]/10',
            border: 'border-[#3B82F6]/30',
            icon: 'text-[#3B82F6]',
            iconBg: 'bg-[#3B82F6]/20',
        },
    };
    return styles[props.type];
});

/**
 * Default titles based on type
 */
const defaultTitle = computed(() => {
    const titles = {
        success: 'Success',
        error: 'Error',
        warning: 'Warning',
        info: 'Info',
    };
    return props.title || titles[props.type];
});

/**
 * Close the toast
 */
const close = () => {
    isVisible.value = false;
    // Wait for animation to complete before emitting
    setTimeout(() => {
        emit('close');
    }, 200);
};

/**
 * Start auto-dismiss timer
 */
const startDismissTimer = () => {
    if (props.duration > 0) {
        dismissTimer = setTimeout(close, props.duration);
    }
};

/**
 * Pause timer on hover
 */
const pauseTimer = () => {
    if (dismissTimer) {
        clearTimeout(dismissTimer);
        dismissTimer = null;
    }
};

/**
 * Resume timer when not hovering
 */
const resumeTimer = () => {
    if (props.duration > 0) {
        startDismissTimer();
    }
};

onMounted(() => {
    // Trigger entrance animation
    requestAnimationFrame(() => {
        isVisible.value = true;
    });
    startDismissTimer();
});
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 translate-y-2 scale-95"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 translate-y-2 scale-95"
    >
        <div
            v-show="isVisible"
            @mouseenter="pauseTimer"
            @mouseleave="resumeTimer"
            :class="['relative flex items-start gap-4 rounded-xl border p-4 shadow-lg backdrop-blur-sm', toastStyles.bg, toastStyles.border]"
            role="alert"
        >
            <!-- Icon -->
            <div :class="['flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full', toastStyles.iconBg]">
                <!-- Success Icon -->
                <svg v-if="type === 'success'" :class="['h-5 w-5', toastStyles.icon]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>

                <!-- Error Icon -->
                <svg v-else-if="type === 'error'" :class="['h-5 w-5', toastStyles.icon]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>

                <!-- Warning Icon -->
                <svg v-else-if="type === 'warning'" :class="['h-5 w-5', toastStyles.icon]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                    />
                </svg>

                <!-- Info Icon -->
                <svg v-else :class="['h-5 w-5', toastStyles.icon]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
            </div>

            <!-- Content -->
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-[#FAFAF9]">
                    {{ defaultTitle }}
                </p>
                <p class="mt-1 text-sm text-[#A8A29E]">
                    {{ message }}
                </p>
            </div>

            <!-- Close Button -->
            <button
                v-if="dismissible"
                @click="close"
                class="flex-shrink-0 rounded-lg p-1 text-[#78716C] transition-colors hover:bg-[#44403C] hover:text-[#FAFAF9]"
                aria-label="Close notification"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Progress bar for auto-dismiss -->
            <div v-if="duration > 0" class="absolute right-0 bottom-0 left-0 h-1 overflow-hidden rounded-b-xl">
                <div
                    :class="['h-full', toastStyles.icon.replace('text-', 'bg-')]"
                    :style="{
                        animation: `shrink ${duration}ms linear forwards`,
                        opacity: 0.5,
                    }"
                ></div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
@keyframes shrink {
    from {
        width: 100%;
    }
    to {
        width: 0%;
    }
}
</style>
