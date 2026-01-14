<script setup lang="ts">
import { Image, Send } from 'lucide-vue-next';
import { ref } from 'vue';

const message = ref('');
const isFocused = ref(false);

const handleSubmit = () => {
    if (!message.value.trim()) return;
    console.log('Send message:', message.value);
    message.value = '';
};

const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSubmit();
    }
};
</script>

<template>
    <div class="border-t p-4" style="background-color: #1b1b1b; border-color: #2b2b2b">
        <div class="mx-auto max-w-4xl">
            <div
                class="relative rounded-xl border transition-colors"
                :style="{
                    backgroundColor: '#202020',
                    borderColor: isFocused ? '#343434' : '#2b2b2b',
                }"
            >
                <input
                    v-model="message"
                    type="text"
                    placeholder="Reply..."
                    class="w-full bg-transparent px-4 py-3 pr-20 text-sm outline-none placeholder:text-[#71717a]"
                    style="color: #f3f4f6"
                    @focus="isFocused = true"
                    @blur="isFocused = false"
                    @keydown="handleKeydown"
                />
                <div class="absolute bottom-2 left-2 flex items-center gap-1">
                    <button class="flex h-8 w-8 items-center justify-center rounded-md transition-colors hover:bg-white/10">
                        <Image class="h-4 w-4" style="color: #71717a" />
                    </button>
                </div>
                <div class="absolute right-2 bottom-2">
                    <button
                        class="flex h-8 w-8 items-center justify-center rounded-full transition-all"
                        :style="{
                            backgroundColor: message.trim() ? '#e07a5f' : '#343434',
                        }"
                        :disabled="!message.trim()"
                        @click="handleSubmit"
                    >
                        <Send class="h-4 w-4" :style="{ color: message.trim() ? '#141414' : '#71717a' }" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
