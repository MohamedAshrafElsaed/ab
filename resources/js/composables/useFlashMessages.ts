/**
 * useFlashMessages Composable
 *
 * Handles Laravel flash messages and converts them to toast notifications.
 * Integrates with Inertia.js page props to detect flash messages.
 *
 * Usage (in your main layout):
 *   import { useFlashMessages } from '@/composables/useFlashMessages';
 *
 *   // In setup or onMounted
 *   useFlashMessages();
 *
 * This will automatically:
 * - Watch for flash messages in page props
 * - Show toast notifications for success, error, warning, info
 * - Handle both simple string messages and structured messages
 *
 * Backend usage (in Laravel controllers):
 *   return redirect()->route('dashboard')->with('success', 'Welcome back!');
 *   return redirect()->route('login')->with('error', 'Authentication failed');
 */

import { router, usePage } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { useToast } from './useToast';

// Flash message types supported by Laravel
export interface FlashMessages {
    success?: string;
    error?: string;
    warning?: string;
    info?: string;
    message?: string; // Generic message (treated as info)
    status?: string; // Status message (used by Fortify, treated as success)
}

/**
 * Check if page props contain flash messages
 */
const getFlashMessages = (props: Record<string, unknown>): FlashMessages => {
    const flash = props.flash as FlashMessages | undefined;
    return flash || {};
};

/**
 * useFlashMessages composable
 *
 * Automatically watches for flash messages and displays toasts.
 * Call this once in your root layout component.
 */
export function useFlashMessages() {
    const page = usePage();
    const toast = useToast();

    /**
     * Process and display flash messages
     */
    const processFlashMessages = () => {
        const flash = getFlashMessages(page.props);

        // Handle success messages
        if (flash.success) {
            toast.success(flash.success);
        }

        // Handle error messages
        if (flash.error) {
            toast.error(flash.error, { duration: 7000 }); // Longer duration for errors
        }

        // Handle warning messages
        if (flash.warning) {
            toast.warning(flash.warning);
        }

        // Handle info messages
        if (flash.info) {
            toast.info(flash.info);
        }

        // Handle generic message (treat as info)
        if (flash.message) {
            toast.info(flash.message);
        }

        // Handle status message (Fortify uses this for password reset, etc.)
        if (flash.status) {
            toast.success(flash.status);
        }
    };

    // Process on mount (for initial page load)
    onMounted(() => {
        processFlashMessages();
    });

    // Watch for page navigation (Inertia will update props)
    router.on('finish', () => {
        processFlashMessages();
    });

    return {
        processFlashMessages,
    };
}

/**
 * Helper to manually trigger flash message processing
 * Useful if you need to process messages outside the composable
 */
export function processFlashFromProps(props: Record<string, unknown>) {
    const toast = useToast();
    const flash = getFlashMessages(props);

    if (flash.success) toast.success(flash.success);
    if (flash.error) toast.error(flash.error, { duration: 7000 });
    if (flash.warning) toast.warning(flash.warning);
    if (flash.info) toast.info(flash.info);
    if (flash.message) toast.info(flash.message);
    if (flash.status) toast.success(flash.status);
}
