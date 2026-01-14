<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuTrigger } from 'reka-ui';

interface Repo {
    name: string;
    owner: string;
    selected: boolean;
}

defineProps<{
    selectedRepo: Repo | undefined;
}>();

const emit = defineEmits<{
    (e: 'suggestionClick', suggestion: string): void;
}>();

const suggestions = [
    {
        id: 'review',
        title: 'Review recent changes',
        description: 'Look at the recent git commits and summarize the key changes, highlighting anything that might need attention or follow-up',
        badge: { type: 'code', text: 'git log', subtext: '3 commits' },
    },
    {
        id: 'error',
        title: 'Add error handling',
        description: 'Find functions that could benefit from better error handling and add appropriate error handling with clear error messages',
        badge: null,
    },
    {
        id: 'feature',
        title: 'Implement a small feature',
        description: 'Look for feature requests in comments, simple enhancements, or obvious missing functionality and implement one',
        badge: { type: 'text', text: '+ Feature', subtext: 'ready to ship 🚀' },
    },
];
</script>

<template>
    <div class="flex min-h-full flex-col items-center justify-center px-6 py-12">
        <!-- Mascot -->
        <div class="mb-8">
            <svg width="80" height="56" viewBox="0 0 80 56" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Body -->
                <rect x="16" y="8" width="48" height="40" rx="4" fill="#e07a5f" />
                <!-- Eyes -->
                <rect x="28" y="20" width="8" height="8" rx="1" fill="#141414" />
                <rect x="44" y="20" width="8" height="8" rx="1" fill="#141414" />
                <!-- Feet -->
                <rect x="24" y="48" width="12" height="8" rx="2" fill="#e07a5f" />
                <rect x="44" y="48" width="12" height="8" rx="2" fill="#e07a5f" />
                <!-- Antenna left -->
                <rect x="24" y="0" width="4" height="12" rx="2" fill="#e07a5f" />
                <circle cx="26" cy="0" r="3" fill="#e07a5f" />
                <!-- Antenna right -->
                <rect x="52" y="0" width="4" height="12" rx="2" fill="#e07a5f" />
                <circle cx="54" cy="0" r="3" fill="#e07a5f" />
            </svg>
        </div>

        <!-- Repo selector row -->
        <div class="mb-8 flex items-center gap-3">
            <DropdownMenuRoot>
                <DropdownMenuTrigger as-child>
                    <button
                        class="flex items-center gap-2 rounded-lg border border-[#2b2b2b] bg-[#1b1b1b] px-4 py-2.5 transition-colors hover:border-[#3a3a3a]"
                    >
                        <span class="text-[13px] text-[#a1a1aa]">
                            {{ selectedRepo ? `${selectedRepo.owner}/${selectedRepo.name}` : 'Select repository' }}
                        </span>
                        <ChevronDown class="h-4 w-4 text-[#666666]" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent
                        :side-offset="4"
                        align="center"
                        class="min-w-[200px] rounded-lg border border-[#2b2b2b] bg-[#202020] p-1 shadow-xl"
                    >
                        <DropdownMenuItem
                            class="cursor-pointer rounded-md px-3 py-2 text-[13px] text-[#a1a1aa] transition-colors outline-none hover:bg-white/5 hover:text-[#f3f4f6]"
                        >
                            MohamedAshrafElsaed/ab
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            class="cursor-pointer rounded-md px-3 py-2 text-[13px] text-[#a1a1aa] transition-colors outline-none hover:bg-white/5 hover:text-[#f3f4f6]"
                        >
                            MohamedAshrafElsaed/AIBuilder
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>

            <DropdownMenuRoot>
                <DropdownMenuTrigger as-child>
                    <button
                        class="flex items-center gap-2 rounded-lg border border-[#2b2b2b] bg-[#1b1b1b] px-4 py-2.5 transition-colors hover:border-[#3a3a3a]"
                    >
                        <span class="text-[13px] text-[#a1a1aa]">Default</span>
                        <ChevronDown class="h-4 w-4 text-[#666666]" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent
                        :side-offset="4"
                        align="center"
                        class="min-w-[120px] rounded-lg border border-[#2b2b2b] bg-[#202020] p-1 shadow-xl"
                    >
                        <DropdownMenuItem
                            class="cursor-pointer rounded-md px-3 py-2 text-[13px] text-[#a1a1aa] transition-colors outline-none hover:bg-white/5 hover:text-[#f3f4f6]"
                        >
                            Default
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            class="cursor-pointer rounded-md px-3 py-2 text-[13px] text-[#a1a1aa] transition-colors outline-none hover:bg-white/5 hover:text-[#f3f4f6]"
                        >
                            Custom
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
        </div>

        <!-- Suggestion cards -->
        <div class="w-full max-w-[480px] space-y-3">
            <button
                v-for="suggestion in suggestions"
                :key="suggestion.id"
                class="group flex w-full items-start justify-between rounded-xl border border-[#2b2b2b] bg-[#1b1b1b] p-4 text-left transition-all hover:border-[#3a3a3a] hover:bg-[#1f1f1f]"
                @click="emit('suggestionClick', suggestion.id)"
            >
                <div class="flex-1 pr-4">
                    <h3 class="mb-1.5 text-[14px] font-medium text-[#f3f4f6]">{{ suggestion.title }}</h3>
                    <p class="text-[13px] leading-relaxed text-[#808080]">{{ suggestion.description }}</p>
                </div>
                <div v-if="suggestion.badge" class="flex flex-shrink-0 flex-col items-end">
                    <template v-if="suggestion.badge.type === 'code'">
                        <code class="rounded bg-[#252525] px-2 py-1 text-[11px] text-[#e07a5f]">{{ suggestion.badge.text }}</code>
                        <span class="mt-1 text-[11px] text-[#666666]">{{ suggestion.badge.subtext }}</span>
                    </template>
                    <template v-else>
                        <span class="text-[11px] text-[#666666]">{{ suggestion.badge.text }}</span>
                        <span class="mt-0.5 text-[11px] text-[#666666]">{{ suggestion.badge.subtext }}</span>
                    </template>
                </div>
            </button>
        </div>
    </div>
</template>
