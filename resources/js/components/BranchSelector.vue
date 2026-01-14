<script setup lang="ts">
import { Check, ChevronDown, GitBranch, Search } from 'lucide-vue-next';
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui';
import { computed, ref } from 'vue';

interface Branch {
    name: string;
    selected: boolean;
}

const props = defineProps<{
    branches: Branch[];
}>();

const emit = defineEmits<{
    (e: 'select', name: string): void;
}>();

const searchQuery = ref('');
const isOpen = ref(false);

const selectedBranch = computed(() => props.branches.find((b) => b.selected));

const filteredBranches = computed(() => props.branches.filter((b) => b.name.toLowerCase().includes(searchQuery.value.toLowerCase())));
</script>

<template>
    <PopoverRoot v-model:open="isOpen">
        <PopoverTrigger as-child>
            <button class="flex items-center gap-1.5 rounded-md px-2 py-1 transition-colors hover:bg-white/5">
                <GitBranch class="h-4 w-4" style="color: #a1a1aa" />
                <span class="text-sm" style="color: #a1a1aa">{{ selectedBranch?.name || 'main' }}</span>
                <ChevronDown class="h-3 w-3" style="color: #71717a" />
            </button>
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent
                :side-offset="8"
                align="start"
                class="w-[220px] rounded-xl border shadow-2xl"
                style="background-color: #202020; border-color: #2b2b2b"
            >
                <!-- Search input -->
                <div class="border-b p-2" style="border-color: #2b2b2b">
                    <div class="flex items-center gap-2 rounded-md px-2 py-1.5" style="background-color: #1b1b1b; border: 1px solid #2b2b2b">
                        <Search class="h-4 w-4" style="color: #71717a" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search branches"
                            class="flex-1 bg-transparent text-sm outline-none placeholder:text-[#71717a]"
                            style="color: #f3f4f6"
                        />
                    </div>
                </div>

                <!-- Branch list -->
                <div class="max-h-[240px] overflow-y-auto p-1">
                    <button
                        v-for="branch in filteredBranches"
                        :key="branch.name"
                        class="flex w-full items-center justify-between rounded-lg px-2 py-2 transition-colors hover:bg-white/5"
                        @click="
                            emit('select', branch.name);
                            isOpen = false;
                        "
                    >
                        <span class="text-sm" style="color: #f3f4f6">{{ branch.name }}</span>
                        <Check v-if="branch.selected" class="h-4 w-4" style="color: #e07a5f" />
                    </button>

                    <div v-if="filteredBranches.length === 0" class="px-2 py-4 text-center">
                        <span class="text-sm" style="color: #71717a">No branches found</span>
                    </div>
                </div>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
