<script setup lang="ts">
import { ref } from 'vue';
interface Props {
    label?: string;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    label: 'Continue with GitHub',
    disabled: false,
});

const isLoading = ref(false);

const handleClick = () => {
    if (isLoading.value || props.disabled) return;

    isLoading.value = true;
    window.location.href = '/auth/github';
};
</script>

<template>
    <button
        type="button"
        :disabled="isLoading || disabled"
        @click="handleClick"
        class="group relative flex w-full items-center justify-center gap-3 rounded-xl border border-[#44403C] bg-[#24292F] px-6 py-4 font-medium text-white shadow-sm transition-all duration-200 hover:bg-[#32383F] hover:shadow-md focus:ring-2 focus:ring-[#E07850]/50 focus:ring-offset-2 focus:ring-offset-[#292524] focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
        :class="{ 'cursor-wait': isLoading }"
    >
        <!-- GitHub Logo -->
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
                    <svg class="h-5 w-5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        />
                    </svg>
                </div>
            </Transition>

            <!-- GitHub Octocat Logo -->
            <svg
                :class="['h-5 w-5 transition-opacity duration-200', isLoading ? 'opacity-0' : 'opacity-100']"
                viewBox="0 0 24 24"
                fill="currentColor"
            >
                <path
                    fill-rule="evenodd"
                    clip-rule="evenodd"
                    d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"
                />
            </svg>
        </div>

        <!-- Button Label -->
        <span class="text-[15px]">
            {{ isLoading ? 'Redirecting...' : label }}
        </span>

        <!-- Arrow indicator -->
        <svg
            :class="[
                'h-4 w-4 text-gray-400 transition-all duration-200',
                isLoading ? 'opacity-0' : 'opacity-100 group-hover:translate-x-1 group-hover:text-white',
            ]"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>

        <!-- Shine effect -->
        <div
            class="pointer-events-none absolute inset-0 overflow-hidden rounded-xl opacity-0 transition-opacity duration-300 group-hover:opacity-100"
        >
            <div
                class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/10 to-transparent transition-transform duration-700 group-hover:translate-x-full"
            ></div>
        </div>
    </button>
</template>
