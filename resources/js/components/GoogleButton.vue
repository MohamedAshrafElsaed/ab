<script setup lang="ts">
/**
 * GoogleButton Component
 *
 * A polished, reusable Google OAuth button with loading states.
 * Redirects to /auth/google which is handled by SocialAuthController.
 *
 * Features:
 * - Official Google branding (white bg, colored logo)
 * - Loading spinner during redirect
 * - Hover and focus states for accessibility
 * - Smooth transitions and animations
 *
 * Usage:
 *   <GoogleButton />
 *   <GoogleButton label="Sign up with Google" />
 */

import { ref } from 'vue';

// Props for customization
interface Props {
    label?: string;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    label: 'Continue with Google',
    disabled: false,
});

// Loading state - tracks when user clicks and waits for redirect
const isLoading = ref(false);

/**
 * Handle button click - redirect to Google OAuth
 * The loading state provides visual feedback during redirect
 */
const handleClick = () => {
    if (isLoading.value || props.disabled) return;

    isLoading.value = true;

    // Redirect to Google OAuth endpoint
    // The backend handles the OAuth flow via SocialAuthController
    window.location.href = '/auth/google';

    // Note: We don't reset isLoading because we're navigating away
    // If the user returns (e.g., cancels OAuth), the page will remount
};
</script>

<template>
    <button
        type="button"
        :disabled="isLoading || disabled"
        @click="handleClick"
        class="group relative flex w-full items-center justify-center gap-3 rounded-xl border border-gray-200 bg-white px-6 py-4 font-medium text-gray-700 shadow-sm transition-all duration-200 hover:bg-gray-50 hover:shadow-md focus:ring-2 focus:ring-[#E07850]/50 focus:ring-offset-2 focus:ring-offset-[#292524] focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
        :class="{ 'cursor-wait': isLoading }"
    >
        <!-- Google Logo (Official colors) -->
        <div class="relative flex-shrink-0">
            <!-- Loading spinner overlay -->
            <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="isLoading" class="absolute inset-0 flex items-center justify-center">
                    <svg class="h-5 w-5 animate-spin text-[#E07850]" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        />
                    </svg>
                </div>
            </Transition>

            <!-- Google "G" Logo -->
            <svg :class="['h-5 w-5 transition-opacity duration-200', isLoading ? 'opacity-0' : 'opacity-100']" viewBox="0 0 24 24">
                <!-- Background -->
                <path
                    d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                    fill="#4285F4"
                />
                <path
                    d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                    fill="#34A853"
                />
                <path
                    d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                    fill="#FBBC05"
                />
                <path
                    d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                    fill="#EA4335"
                />
            </svg>
        </div>

        <!-- Button Label -->
        <span class="text-[15px]">
            {{ isLoading ? 'Redirecting...' : label }}
        </span>

        <!-- Subtle arrow indicator -->
        <svg
            :class="[
                'h-4 w-4 text-gray-400 transition-all duration-200',
                isLoading ? 'opacity-0' : 'opacity-100 group-hover:translate-x-1 group-hover:text-gray-600',
            ]"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>

        <!-- Shine effect on hover -->
        <div
            class="pointer-events-none absolute inset-0 overflow-hidden rounded-xl opacity-0 transition-opacity duration-300 group-hover:opacity-100"
        >
            <div
                class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/20 to-transparent transition-transform duration-700 group-hover:translate-x-full"
            ></div>
        </div>
    </button>
</template>

<style scoped>
/* Ensure consistent button appearance across browsers */
button {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}
</style>
