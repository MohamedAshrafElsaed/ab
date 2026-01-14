<script setup lang="ts">
import { Check, Filter, GitMerge } from 'lucide-vue-next';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuTrigger } from 'reka-ui';
import { ref } from 'vue';

interface Session {
    id: string;
    title: string;
    project: string;
    time: string;
    active: boolean;
    metrics: { additions: number; deletions: number } | null;
}

defineProps<{
    sessions: Session[];
}>();

const emit = defineEmits<{
    (e: 'select', id: string): void;
}>();

const filterOptions = ['Active', 'Archived', 'All'];
const selectedFilter = ref('Active');
</script>

<template>
    <div class="flex flex-1 flex-col overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-3 py-2">
            <span class="text-xs font-medium tracking-wider uppercase" style="color: #71717a">Sessions</span>
            <DropdownMenuRoot>
                <DropdownMenuTrigger as-child>
                    <button class="flex h-6 w-6 items-center justify-center rounded transition-colors hover:bg-white/5">
                        <Filter class="h-3.5 w-3.5" style="color: #71717a" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent
                        :side-offset="4"
                        align="end"
                        class="min-w-[120px] rounded-lg border p-1 shadow-xl"
                        style="background-color: #202020; border-color: #2b2b2b"
                    >
                        <DropdownMenuItem
                            v-for="option in filterOptions"
                            :key="option"
                            class="flex cursor-pointer items-center justify-between rounded-md px-2 py-1.5 text-sm transition-colors outline-none hover:bg-white/5"
                            :style="{ color: selectedFilter === option ? '#f3f4f6' : '#a1a1aa' }"
                            @click="selectedFilter = option"
                        >
                            {{ option }}
                            <Check v-if="selectedFilter === option" class="h-3.5 w-3.5" style="color: #e07a5f" />
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
        </div>

        <!-- Session list -->
        <div class="flex-1 overflow-y-auto px-2">
            <button
                v-for="session in sessions"
                :key="session.id"
                class="group mb-1 flex w-full flex-col rounded-lg px-2 py-2 text-left transition-all"
                :class="session.active ? 'border-l-2' : 'border-l-2 border-transparent'"
                :style="{
                    backgroundColor: session.active ? '#202020' : 'transparent',
                    borderLeftColor: session.active ? '#e07a5f' : 'transparent',
                }"
                @click="emit('select', session.id)"
            >
                <div class="flex w-full items-start justify-between gap-2">
                    <span class="line-clamp-2 text-sm font-medium transition-colors" :style="{ color: session.active ? '#f3f4f6' : '#a1a1aa' }">
                        {{ session.title }}
                    </span>
                    <span v-if="session.active" class="mt-0.5 h-1.5 w-1.5 flex-shrink-0 rounded-full" style="background-color: #e07a5f" />
                </div>
                <div class="mt-1 flex items-center gap-2">
                    <span class="text-xs" style="color: #71717a">{{ session.project }}</span>
                    <span class="text-xs" style="color: #71717a">·</span>
                    <span class="text-xs" style="color: #71717a">{{ session.time }}</span>
                    <template v-if="session.metrics">
                        <span class="text-xs" style="color: #71717a">·</span>
                        <span class="text-xs" style="color: #22c55e">+{{ session.metrics.additions }}</span>
                        <span class="text-xs" style="color: #ef4444">-{{ session.metrics.deletions }}</span>
                        <GitMerge class="h-3 w-3" style="color: #71717a" />
                    </template>
                </div>
            </button>
        </div>
    </div>
</template>

<style scoped>
button:hover:not(.border-l-2) {
    background-color: rgba(255, 255, 255, 0.03);
}
</style>
