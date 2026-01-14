<script setup lang="ts">
import { Check, ChevronDown, Copy, ExternalLink } from 'lucide-vue-next';
import { TooltipContent, TooltipPortal, TooltipProvider, TooltipRoot, TooltipTrigger } from 'reka-ui';
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
    session: Session | undefined;
}>();

const branchName = 'claude/update-color-scheme';
const copied = ref(false);

const copyBranchName = () => {
    navigator.clipboard.writeText(branchName);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
};
</script>

<template>
    <header class="hidden h-12 items-center justify-between border-b px-4 lg:flex" style="background-color: #1b1b1b; border-color: #2b2b2b">
        <!-- Left: Session title with dropdown -->
        <div class="flex items-center gap-2">
            <div class="flex h-5 w-5 items-center justify-center">
                <div
                    class="h-3 w-3 animate-spin rounded-full border-2 border-t-transparent"
                    style="border-color: #e07a5f; border-top-color: transparent"
                />
            </div>
            <button class="flex items-center gap-1.5 rounded-md px-2 py-1 transition-colors hover:bg-white/5">
                <span class="max-w-[300px] truncate text-sm font-medium" style="color: #f3f4f6">
                    {{ session?.title || 'New Session' }}
                </span>
                <ChevronDown class="h-3.5 w-3.5" style="color: #71717a" />
            </button>
        </div>

        <!-- Right: Branch pill + actions -->
        <div class="flex items-center gap-3">
            <!-- Branch pill -->
            <span class="rounded-md px-2 py-1 font-mono text-xs" style="background-color: #202020; color: #a1a1aa; border: 1px solid #2b2b2b">
                {{ branchName }}
            </span>

            <!-- Copy branch name button with tooltip -->
            <TooltipProvider>
                <TooltipRoot :delay-duration="100">
                    <TooltipTrigger as-child>
                        <button
                            class="flex h-7 w-7 items-center justify-center rounded-md transition-colors hover:bg-white/10"
                            @click="copyBranchName"
                        >
                            <Check v-if="copied" class="h-4 w-4" style="color: #22c55e" />
                            <Copy v-else class="h-4 w-4" style="color: #71717a" />
                        </button>
                    </TooltipTrigger>
                    <TooltipPortal>
                        <TooltipContent
                            :side-offset="8"
                            class="rounded-md px-2 py-1 text-xs shadow-lg"
                            style="background-color: #343434; color: #f3f4f6"
                        >
                            {{ copied ? 'Copied!' : 'Copy branch name' }}
                        </TooltipContent>
                    </TooltipPortal>
                </TooltipRoot>
            </TooltipProvider>

            <!-- Open in CLI button -->
            <button
                class="flex items-center gap-2 rounded-md px-3 py-1.5 transition-colors hover:bg-white/10"
                style="background-color: #202020; border: 1px solid #2b2b2b"
            >
                <span class="text-sm" style="color: #a1a1aa">Open in CLI</span>
                <ExternalLink class="h-3.5 w-3.5" style="color: #71717a" />
            </button>
        </div>
    </header>
</template>
