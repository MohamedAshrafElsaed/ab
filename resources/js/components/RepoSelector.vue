<script setup lang="ts">
import { Check, ChevronDown, Github, Search } from 'lucide-vue-next';
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui';
import { computed, ref } from 'vue';

interface Repo {
    name: string;
    owner: string;
    selected: boolean;
}

const props = defineProps<{
    repos: Repo[];
}>();

const emit = defineEmits<{
    (e: 'select', name: string): void;
}>();

const searchQuery = ref('');
const isOpen = ref(false);

const selectedRepo = computed(() => props.repos.find((r) => r.selected));

const recentRepos = computed(() => props.repos.slice(0, 3));
const allRepos = computed(() => props.repos.slice(3));

const filteredRecentRepos = computed(() =>
    recentRepos.value.filter(
        (r) => r.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || r.owner.toLowerCase().includes(searchQuery.value.toLowerCase()),
    ),
);

const filteredAllRepos = computed(() =>
    allRepos.value.filter(
        (r) => r.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || r.owner.toLowerCase().includes(searchQuery.value.toLowerCase()),
    ),
);
</script>

<template>
    <PopoverRoot v-model:open="isOpen">
        <PopoverTrigger as-child>
            <button class="flex items-center gap-1.5 rounded-md px-2 py-1 transition-colors hover:bg-white/5">
                <Github class="h-4 w-4" style="color: #a1a1aa" />
                <span class="text-sm" style="color: #a1a1aa">{{ selectedRepo?.name || 'Select repo' }}</span>
                <ChevronDown class="h-3 w-3" style="color: #71717a" />
            </button>
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent
                :side-offset="8"
                align="start"
                class="w-[280px] rounded-xl border shadow-2xl"
                style="background-color: #202020; border-color: #2b2b2b"
            >
                <!-- Search input -->
                <div class="border-b p-2" style="border-color: #2b2b2b">
                    <div class="flex items-center gap-2 rounded-md px-2 py-1.5" style="background-color: #1b1b1b; border: 1px solid #2b2b2b">
                        <Search class="h-4 w-4" style="color: #71717a" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search repositories"
                            class="flex-1 bg-transparent text-sm outline-none placeholder:text-[#71717a]"
                            style="color: #f3f4f6"
                        />
                    </div>
                </div>

                <!-- Repository list -->
                <div class="max-h-[300px] overflow-y-auto p-1">
                    <!-- Recently Used section -->
                    <div v-if="filteredRecentRepos.length > 0" class="mb-2">
                        <span class="px-2 py-1 text-[10px] font-medium tracking-wider uppercase" style="color: #71717a"> Recently Used </span>
                        <button
                            v-for="repo in filteredRecentRepos"
                            :key="repo.name"
                            class="mt-1 flex w-full items-center justify-between rounded-lg px-2 py-2 transition-colors hover:bg-white/5"
                            @click="
                                emit('select', repo.name);
                                isOpen = false;
                            "
                        >
                            <div class="flex flex-col items-start">
                                <span class="text-sm font-medium" style="color: #f3f4f6">{{ repo.name }}</span>
                                <span class="text-xs" style="color: #71717a">{{ repo.owner }}</span>
                            </div>
                            <Check v-if="repo.selected" class="h-4 w-4" style="color: #e07a5f" />
                        </button>
                    </div>

                    <!-- All Repositories section -->
                    <div v-if="filteredAllRepos.length > 0">
                        <span class="px-2 py-1 text-[10px] font-medium tracking-wider uppercase" style="color: #71717a"> All Repositories </span>
                        <button
                            v-for="repo in filteredAllRepos"
                            :key="repo.name"
                            class="mt-1 flex w-full items-center justify-between rounded-lg px-2 py-2 transition-colors hover:bg-white/5"
                            @click="
                                emit('select', repo.name);
                                isOpen = false;
                            "
                        >
                            <div class="flex flex-col items-start">
                                <span class="text-sm font-medium" style="color: #f3f4f6">{{ repo.name }}</span>
                                <span class="text-xs" style="color: #71717a">{{ repo.owner }}</span>
                            </div>
                            <Check v-if="repo.selected" class="h-4 w-4" style="color: #e07a5f" />
                        </button>
                    </div>
                </div>

                <!-- Footer CTA -->
                <div class="border-t p-2" style="border-color: #2b2b2b">
                    <p class="mb-2 px-2 text-xs" style="color: #71717a">
                        Repo missing? Install the Claude GitHub app in a private repository to access it here.
                    </p>
                    <button
                        class="flex w-full items-center justify-center gap-2 rounded-lg py-2 transition-colors hover:bg-white/5"
                        style="background-color: #2b2b2b"
                    >
                        <Github class="h-4 w-4" style="color: #a1a1aa" />
                        <span class="text-sm font-medium" style="color: #f3f4f6">Install GitHub App</span>
                    </button>
                </div>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
