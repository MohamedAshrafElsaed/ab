<script setup lang="ts">
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Sidebar from '@/components/Sidebar.vue';
import WelcomeScreen from './WelcomeScreen.vue';
import SessionView from './SessionView.vue';

const page = usePage();
const appName = computed(() => page.props.name || 'APP_NAME');

// State
const currentView = ref<'welcome' | 'session'>('welcome');
const sidebarOpen = ref(false);

// Mock data
const repos = ref([
    { name: 'ab', owner: 'MohamedAshrafElsaed', selected: true },
    { name: 'AIBuilder', owner: 'MohamedAshrafElsaed', selected: false },
    { name: 'ConvertedOrders', owner: 'convertedin', selected: false },
]);

const branches = ref([
    { name: 'main', selected: true },
    { name: 'develop', selected: false },
]);

const sessions = ref([
    { id: '1', title: 'Update app color scheme to match design', project: 'ab', time: 'Wed', active: false, metrics: null },
    { id: '2', title: 'Update Welcome page and app colors', project: 'ab', time: 'Wed', active: false, metrics: null },
    { id: '3', title: 'Fix critical production bugs', project: 'ConvertedOrders', time: 'Wed', active: false, metrics: { additions: 0, deletions: 27 } },
]);

const user = ref({
    email: 'm.ashraf@converted.in',
    name: 'Mohamed Ashraf',
    workspaces: [
        { name: 'Convertedin', plan: 'Team plan', selected: true },
        { name: 'Personal', plan: 'Free plan', selected: false },
    ],
});

const selectedRepo = computed(() => repos.value.find(r => r.selected));

// Actions
const selectSession = (id: string) => {
    sessions.value = sessions.value.map(s => ({ ...s, active: s.id === id }));
    currentView.value = 'session';
};

const selectRepo = (name: string) => {
    repos.value = repos.value.map(r => ({ ...r, selected: r.name === name }));
};

const selectBranch = (name: string) => {
    branches.value = branches.value.map(b => ({ ...b, selected: b.name === name }));
};

const handleSuggestionClick = (suggestion: string) => {
    console.log('Suggestion clicked:', suggestion);
    currentView.value = 'session';
};
</script>

<template>
    <div class="flex h-screen w-full overflow-hidden bg-[#141414]">
        <!-- Mobile backdrop -->
        <Transition name="fade">
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-40 bg-black/60 lg:hidden"
                @click="sidebarOpen = false"
            />
        </Transition>

        <!-- Sidebar -->
        <Sidebar
            :app-name="appName"
            :repos="repos"
            :branches="branches"
            :sessions="sessions"
            :user="user"
            :mobile-open="sidebarOpen"
            @close-mobile="sidebarOpen = false"
            @select-session="selectSession"
            @select-repo="selectRepo"
            @select-branch="selectBranch"
        />

        <!-- Main content -->
        <main class="relative flex flex-1 flex-col overflow-hidden">
            <!-- Mobile header -->
            <header class="flex h-12 items-center gap-3 border-b border-[#2b2b2b] bg-[#1b1b1b] px-4 lg:hidden">
                <button
                    class="flex h-8 w-8 items-center justify-center rounded-md text-[#a1a1aa] transition-colors hover:bg-white/5"
                    @click="sidebarOpen = true"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <span class="text-sm font-medium text-[#f3f4f6]">{{ appName }}</span>
            </header>

            <!-- Content area -->
            <div class="flex-1 overflow-y-auto">
                <WelcomeScreen
                    v-if="currentView === 'welcome'"
                    :selected-repo="selectedRepo"
                    @suggestion-click="handleSuggestionClick"
                />
                <SessionView v-else />
            </div>
        </main>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
