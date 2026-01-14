<script lang="ts" setup>
import ToastContainer from '@/components/ToastContainer.vue';
import { useFlashMessages } from '@/composables/useFlashMessages';
import { onMounted } from 'vue';

useFlashMessages();
onMounted(() => {});
</script>

<template>
    <div class="min-h-screen bg-[#1C1917]">
        <slot />
        <ToastContainer :max-toasts="5" position="top-right" />
    </div>
</template>

<style>
html {
    scroll-behavior: smooth;
}

::selection {
    background-color: rgba(224, 120, 80, 0.3);
    color: #fafaf9;
}

:focus-visible {
    outline: 2px solid #e07850;
    outline-offset: 2px;
}
</style>
