<script setup lang="ts">
/**
 * ToastContainer Component
 *
 * A container component for displaying toast notifications.
 * Should be placed in the root layout (App.vue or main layout).
 * Works with the useToast composable for global toast management.
 *
 * Usage (in your main layout):
 *   <template>
 *       <div>
 *           <!-- Your app content -->
 *           <slot />
 *
 *           <!-- Toast container -->
 *           <ToastContainer />
 *       </div>
 *   </template>
 *
 * Then anywhere in your app:
 *   import { useToast } from '@/composables/useToast';
 *   const toast = useToast();
 *   toast.success('Welcome back, John!');
 */

import { useToast } from '@/composables/useToast';
import Toast from './Toast.vue';

// Position options
type Position = 'top-right' | 'top-left' | 'bottom-right' | 'bottom-left' | 'top-center' | 'bottom-center';

interface Props {
    position?: Position;
    maxToasts?: number;
}

const props = withDefaults(defineProps<Props>(), {
    position: 'top-right',
    maxToasts: 5,
});

const { toasts, remove } = useToast();

/**
 * Position classes based on prop
 */
const positionClasses: Record<Position, string> = {
    'top-right': 'top-4 right-4',
    'top-left': 'top-4 left-4',
    'bottom-right': 'bottom-4 right-4',
    'bottom-left': 'bottom-4 left-4',
    'top-center': 'top-4 left-1/2 -translate-x-1/2',
    'bottom-center': 'bottom-4 left-1/2 -translate-x-1/2',
};

/**
 * Handle toast close
 */
const handleClose = (id: string) => {
    remove(id);
};
</script>

<template>
    <Teleport to="body">
        <div
            :class="['pointer-events-none fixed z-[100] flex w-full max-w-sm flex-col gap-3', positionClasses[position]]"
            aria-live="polite"
            aria-label="Notifications"
        >
            <TransitionGroup
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 translate-x-4 scale-95"
                enter-to-class="opacity-100 translate-x-0 scale-100"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-x-0 scale-100"
                leave-to-class="opacity-0 translate-x-4 scale-95"
                move-class="transition-all duration-300"
            >
                <div v-for="toast in toasts.slice(0, maxToasts)" :key="toast.id" class="pointer-events-auto">
                    <Toast
                        :type="toast.type"
                        :message="toast.message"
                        :title="toast.title"
                        :duration="0"
                        :dismissible="toast.dismissible"
                        @close="handleClose(toast.id)"
                    />
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
