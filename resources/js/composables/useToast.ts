/**
 * useToast Composable
 *
 * A composable for managing toast notifications throughout the application.
 * Uses a reactive store pattern for global toast state.
 *
 * Usage:
 *   import { useToast } from '@/composables/useToast';
 *
 *   const toast = useToast();
 *
 *   // Show different types of toasts
 *   toast.success('Account created successfully!');
 *   toast.error('Authentication failed');
 *   toast.warning('Your session will expire soon');
 *   toast.info('New features available');
 *
 *   // Custom options
 *   toast.success('Welcome back!', { title: 'Hello!', duration: 3000 });
 */

import { reactive, readonly } from 'vue';

// Toast types
export type ToastType = 'success' | 'error' | 'warning' | 'info';

// Toast configuration
export interface ToastOptions {
    title?: string;
    duration?: number;
    dismissible?: boolean;
}

// Individual toast item
export interface ToastItem {
    id: string;
    type: ToastType;
    message: string;
    title?: string;
    duration: number;
    dismissible: boolean;
}

// Default options
const DEFAULT_OPTIONS: Required<Omit<ToastOptions, 'title'>> = {
    duration: 5000,
    dismissible: true,
};

// Global toast state (singleton pattern)
const state = reactive<{
    toasts: ToastItem[];
}>({
    toasts: [],
});

// Generate unique IDs
let idCounter = 0;
const generateId = (): string => `toast-${++idCounter}-${Date.now()}`;

/**
 * Add a toast to the stack
 */
const addToast = (type: ToastType, message: string, options: ToastOptions = {}): string => {
    const id = generateId();

    const toast: ToastItem = {
        id,
        type,
        message,
        title: options.title,
        duration: options.duration ?? DEFAULT_OPTIONS.duration,
        dismissible: options.dismissible ?? DEFAULT_OPTIONS.dismissible,
    };

    state.toasts.push(toast);

    // Auto-remove after duration (if duration > 0)
    if (toast.duration > 0) {
        setTimeout(() => {
            removeToast(id);
        }, toast.duration + 300); // Add buffer for exit animation
    }

    return id;
};

/**
 * Remove a toast by ID
 */
const removeToast = (id: string): void => {
    const index = state.toasts.findIndex((t) => t.id === id);
    if (index !== -1) {
        state.toasts.splice(index, 1);
    }
};

/**
 * Clear all toasts
 */
const clearAll = (): void => {
    state.toasts = [];
};

/**
 * useToast composable
 * Returns methods for showing toasts and reactive state
 */
export function useToast() {
    return {
        // Reactive state (readonly to prevent external mutations)
        toasts: readonly(state).toasts,

        // Show success toast
        success: (message: string, options?: ToastOptions) => addToast('success', message, options),

        // Show error toast
        error: (message: string, options?: ToastOptions) => addToast('error', message, options),

        // Show warning toast
        warning: (message: string, options?: ToastOptions) => addToast('warning', message, options),

        // Show info toast
        info: (message: string, options?: ToastOptions) => addToast('info', message, options),

        // Generic show method
        show: (type: ToastType, message: string, options?: ToastOptions) => addToast(type, message, options),

        // Remove specific toast
        remove: removeToast,

        // Clear all toasts
        clearAll,
    };
}

// Export singleton instance for direct imports
export const toast = {
    success: (message: string, options?: ToastOptions) => addToast('success', message, options),
    error: (message: string, options?: ToastOptions) => addToast('error', message, options),
    warning: (message: string, options?: ToastOptions) => addToast('warning', message, options),
    info: (message: string, options?: ToastOptions) => addToast('info', message, options),
};
