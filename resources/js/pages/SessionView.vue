<script setup lang="ts">
import { Check, ChevronDown, Circle, Copy, ExternalLink, Image, Send, Sparkles } from 'lucide-vue-next';
import { TooltipContent, TooltipPortal, TooltipProvider, TooltipRoot, TooltipTrigger } from 'reka-ui';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const loadingStatuses = ['Thinking...', 'Processing...', 'Generating...', 'Shimmying...', 'Clauding...'];

const currentStatusIndex = ref(0);
const isLoading = ref(true);
const copied = ref(false);
const replyMessage = ref('');
const branchName = 'claude/update-color-scheme';

let statusInterval: number | null = null;

const currentLoadingStatus = computed(() => {
    return loadingStatuses[currentStatusIndex.value];
});

onMounted(() => {
    statusInterval = window.setInterval(() => {
        currentStatusIndex.value = (currentStatusIndex.value + 1) % loadingStatuses.length;
    }, 1500);

    setTimeout(() => {
        isLoading.value = false;
    }, 3000);
});

onUnmounted(() => {
    if (statusInterval !== null) {
        window.clearInterval(statusInterval);
    }
});

const copyBranchName = () => {
    navigator.clipboard.writeText(branchName);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
};

interface ActivityItem {
    type: 'glob' | 'read' | 'grep' | 'todo';
    path?: string;
    pattern?: string;
    lines?: number;
    items?: string[];
}

const activity: ActivityItem[] = [
    { type: 'glob', path: '**/Notifications/**/*.php' },
    {
        type: 'read',
        path: '/home/user/ConvertedOrders/app/Notifications/BaseNotification.php',
        lines: 227,
    },
    { type: 'grep', pattern: 'implements ShouldQueue|onQueue|queue' },
    {
        type: 'todo',
        items: ['Creating VerifyEmail notification', 'Override sendEmailVerificationNotification in User model', 'Test and commit changes'],
    },
];

interface DiffLine {
    type: 'add' | 'remove' | 'neutral';
    num: number;
    code: string;
}

const diffLines: DiffLine[] = [
    { type: 'add', num: 1, code: '<?php' },
    { type: 'add', num: 2, code: '' },
    { type: 'add', num: 3, code: 'namespace App\\Notifications\\User\\Auth;' },
    { type: 'add', num: 4, code: '' },
    { type: 'add', num: 5, code: 'use Illuminate\\Auth\\Notifications\\VerifyEmail;' },
    { type: 'add', num: 6, code: 'use Illuminate\\Bus\\Queueable;' },
    { type: 'add', num: 7, code: 'use Illuminate\\Contracts\\Queue\\ShouldQueue;' },
    { type: 'add', num: 8, code: 'use Illuminate\\Notifications\\Messages\\MailMessage;' },
    { type: 'add', num: 9, code: 'use Illuminate\\Support\\Carbon;' },
    { type: 'add', num: 10, code: 'use Illuminate\\Support\\Facades\\Config;' },
    { type: 'add', num: 11, code: 'use Illuminate\\Support\\Facades\\URL;' },
    { type: 'add', num: 12, code: '' },
    { type: 'add', num: 13, code: 'class VerifyEmailNotification extends VerifyEmail implements ShouldQueue' },
    { type: 'add', num: 14, code: '{' },
    { type: 'add', num: 15, code: '    use Queueable;' },
    { type: 'add', num: 16, code: '' },
    { type: 'add', num: 17, code: '    public int $tries = 3;' },
    { type: 'add', num: 18, code: '    public int $backoff = 30;' },
    { type: 'add', num: 19, code: '    public int $timeout = 90;' },
    { type: 'add', num: 20, code: '' },
];

const getActionLabel = (type: string): string => {
    const labels: Record<string, string> = {
        read: 'Read',
        grep: 'Grep',
        glob: 'Glob',
    };
    return labels[type] || type;
};
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Top bar -->
        <header class="flex h-12 items-center justify-between border-b border-[#2b2b2b] bg-[#1b1b1b] px-4">
            <div class="flex items-center gap-2">
                <div class="flex h-5 w-5 items-center justify-center">
                    <div class="h-3 w-3 animate-spin rounded-full border-2 border-[#e07a5f] border-t-transparent" />
                </div>
                <button class="flex items-center gap-1.5 rounded-md px-2 py-1 transition-colors hover:bg-white/5">
                    <span class="max-w-[300px] truncate text-[13px] font-medium text-[#E0E0DE]"> Update app color scheme to match design </span>
                    <ChevronDown class="h-3.5 w-3.5 text-[#666666]" />
                </button>
            </div>
            <div class="flex items-center gap-3">
                <span class="rounded-md border border-[#2b2b2b] bg-[#202020] px-2.5 py-1 font-mono text-[11px] text-[#a1a1aa]">
                    {{ branchName }}
                </span>
                <TooltipProvider>
                    <TooltipRoot :delay-duration="100">
                        <TooltipTrigger as-child>
                            <button
                                class="flex h-7 w-7 items-center justify-center rounded-md transition-colors hover:bg-white/10"
                                @click="copyBranchName"
                            >
                                <Check v-if="copied" class="h-4 w-4 text-[#4ade80]" />
                                <Copy v-else class="h-4 w-4 text-[#666666]" />
                            </button>
                        </TooltipTrigger>
                        <TooltipPortal>
                            <TooltipContent :side-offset="8" class="rounded-md bg-[#343434] px-2 py-1 text-[11px] text-[#E0E0DE] shadow-lg">
                                {{ copied ? 'Copied!' : 'Copy branch name' }}
                            </TooltipContent>
                        </TooltipPortal>
                    </TooltipRoot>
                </TooltipProvider>
                <button
                    class="flex items-center gap-2 rounded-md border border-[#2b2b2b] bg-[#202020] px-3 py-1.5 transition-colors hover:bg-[#252525]"
                >
                    <span class="text-[13px] text-[#a1a1aa]">Open in CLI</span>
                    <ExternalLink class="h-3.5 w-3.5 text-[#666666]" />
                </button>
            </div>
        </header>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto bg-[#141414]">
            <div class="mx-auto max-w-3xl px-6 py-8">
                <!-- Loading state -->
                <div v-if="isLoading" class="flex items-center justify-center py-32">
                    <div class="flex items-center gap-2">
                        <Sparkles class="loading-pulse h-4 w-4 text-[#e07a5f]" />
                        <span class="loading-shimmer text-[14px] font-medium text-[#e07a5f]">
                            {{ currentLoadingStatus }}
                        </span>
                    </div>
                </div>

                <!-- Activity feed -->
                <div v-else class="space-y-4">
                    <!-- Activity items -->
                    <div v-for="(item, idx) in activity" :key="idx" class="flex items-start gap-3">
                        <div class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-[#4ade80]" />
                        <div class="flex-1">
                            <!-- Todo type -->
                            <template v-if="item.type === 'todo'">
                                <span class="text-[13px] font-medium text-[#E0E0DE]">Update Todos</span>
                                <div v-if="item.items" class="mt-2 space-y-1.5 pl-1">
                                    <div v-for="(todo, i) in item.items" :key="i" class="flex items-center gap-2">
                                        <Circle class="h-3 w-3 text-[#666666]" />
                                        <span class="text-[13px] text-[#a1a1aa]">{{ todo }}</span>
                                    </div>
                                </div>
                            </template>
                            <!-- Other types -->
                            <template v-else>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-[13px] font-medium text-[#E0E0DE]">
                                        {{ getActionLabel(item.type) }}
                                    </span>
                                    <code v-if="item.path" class="rounded bg-[#202020] px-1.5 py-0.5 text-[11px] text-[#a1a1aa]">
                                        {{ item.path }}
                                    </code>
                                    <code v-if="item.pattern" class="rounded bg-[#202020] px-1.5 py-0.5 text-[11px] text-[#a1a1aa]">
                                        {{ item.pattern }}
                                    </code>
                                </div>
                                <div v-if="item.lines" class="mt-1 pl-1">
                                    <span class="text-[11px] text-[#666666]"> Read {{ item.lines }} lines </span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Write action with diff -->
                    <div class="flex items-start gap-3">
                        <div class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-[#4ade80]" />
                        <div class="flex-1">
                            <p class="mb-2 text-[13px] text-[#E0E0DE]">
                                Now I'll create a custom VerifyEmail notification that uses the
                                <code class="rounded bg-[#202020] px-1.5 py-0.5 text-[11px] text-[#e07a5f]">emails</code>
                                queue:
                            </p>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[13px] font-medium text-[#E0E0DE]">Write</span>
                                <code class="rounded bg-[#202020] px-1.5 py-0.5 text-[11px] text-[#a1a1aa]">
                                    /home/user/.../VerifyEmailNotification.php
                                </code>
                            </div>

                            <!-- Code diff -->
                            <div class="mt-3 overflow-hidden rounded-lg border border-[#2b2b2b] bg-[#1b1b1b]">
                                <div class="overflow-x-auto">
                                    <table class="w-full font-mono text-[11px]">
                                        <tbody>
                                            <tr v-for="line in diffLines" :key="line.num" class="bg-[#4ade80]/5">
                                                <td class="w-10 border-r border-[#2b2b2b] px-2 py-0.5 text-right text-[#666666] select-none">
                                                    {{ line.num }}
                                                </td>
                                                <td class="w-5 px-1 py-0.5 text-center text-[#4ade80] select-none">+</td>
                                                <td class="px-2 py-0.5 whitespace-pre text-[#4ade80]">
                                                    {{ line.code }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="border-t border-[#2b2b2b] px-3 py-2">
                                    <button class="text-[11px] text-[#666666] transition-colors hover:text-[#888888] hover:underline">
                                        Show full diff (110 more lines)
                                    </button>
                                </div>
                            </div>

                            <div class="mt-3 flex justify-end">
                                <button
                                    class="flex items-center gap-2 rounded-lg bg-[#e07a5f] px-4 py-2 text-[13px] font-medium text-[#141414] transition-colors hover:bg-[#d66b50]"
                                >
                                    View PR
                                    <ExternalLink class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Final todo -->
                    <div class="flex items-start gap-3">
                        <div class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-[#4ade80]" />
                        <span class="text-[13px] font-medium text-[#E0E0DE]">Update Todos</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reply composer -->
        <div class="border-t border-[#2b2b2b] bg-[#1b1b1b] p-4">
            <div class="mx-auto max-w-3xl">
                <div class="relative rounded-xl border border-[#2b2b2b] bg-[#202020] transition-colors focus-within:border-[#3a3a3a]">
                    <input
                        v-model="replyMessage"
                        type="text"
                        placeholder="Reply..."
                        class="w-full bg-transparent px-4 py-3.5 pr-20 text-[13px] text-[#E0E0DE] outline-none placeholder:text-[#666666]"
                    />
                    <div class="absolute bottom-2.5 left-2.5">
                        <button
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-[#666666] transition-colors hover:bg-white/5 hover:text-[#888888]"
                        >
                            <Image class="h-[18px] w-[18px]" />
                        </button>
                    </div>
                    <div class="absolute right-2.5 bottom-2.5">
                        <button
                            class="flex h-8 w-8 items-center justify-center rounded-full transition-all"
                            :class="replyMessage.trim() ? 'bg-[#e07a5f] text-[#141414]' : 'bg-[#3a3a3a] text-[#666666]'"
                        >
                            <Send class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.loading-shimmer {
    animation: shimmer 1.5s ease-in-out infinite;
}

.loading-pulse {
    animation: pulse 1.5s ease-in-out infinite;
}

@keyframes shimmer {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.6;
    }
}

@keyframes pulse {
    0%,
    100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.6;
        transform: scale(0.95);
    }
}
</style>
