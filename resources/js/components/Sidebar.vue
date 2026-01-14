<script setup lang="ts">
import { ref } from 'vue';
import { X, Image, MoreHorizontal, Send, Github, GitBranch, RefreshCw, Filter, Check, ChevronDown, Search, Settings, Globe, HelpCircle, CreditCard, BookOpen, LogOut, ChevronRight, Users, User } from 'lucide-vue-next';
import {
    PopoverRoot,
    PopoverTrigger,
    PopoverPortal,
    PopoverContent,
    DropdownMenuRoot,
    DropdownMenuTrigger,
    DropdownMenuPortal,
    DropdownMenuContent,
    DropdownMenuItem,
} from 'reka-ui';

interface Repo { name: string; owner: string; selected: boolean }
interface Branch { name: string; selected: boolean }
interface Session { id: string; title: string; project: string; time: string; active: boolean; metrics: { additions: number; deletions: number } | null }
interface Workspace { name: string; plan: string; selected: boolean }
interface UserData { email: string; name: string; workspaces: Workspace[] }

const props = defineProps<{
    appName: string;
    repos: Repo[];
    branches: Branch[];
    sessions: Session[];
    user: UserData;
    mobileOpen: boolean;
}>();

const emit = defineEmits<{
    (e: 'closeMobile'): void;
    (e: 'selectSession', id: string): void;
    (e: 'selectRepo', name: string): void;
    (e: 'selectBranch', name: string): void;
}>();

const composerValue = ref('');
const repoSearchQuery = ref('');
const branchSearchQuery = ref('');
const filterOption = ref('Active');
const repoOpen = ref(false);
const branchOpen = ref(false);

const selectedRepo = () => props.repos.find(r => r.selected);
const selectedBranch = () => props.branches.find(b => b.selected);
</script>

<template>
    <aside
        :class="[
            'fixed inset-y-0 left-0 z-50 flex w-[480px] flex-col border-r border-[#2b2b2b] bg-[#1b1b1b] transition-transform duration-300 lg:relative lg:translate-x-0',
            mobileOpen ? 'translate-x-0' : '-translate-x-full',
        ]"
    >
        <!-- Header -->
        <div class="flex h-14 items-center justify-between px-4">
            <div class="flex items-center gap-2.5">
                <span class="text-[15px] font-semibold text-[#f3f4f6]">{{ appName }}</span>
                <span class="rounded-[4px] border border-[#3a3a3a] bg-[#252525] px-1.5 py-0.5 text-[10px] font-medium text-[#808080]">
                    Research preview
                </span>
            </div>
            <button class="flex h-7 w-7 items-center justify-center rounded-md text-[#a1a1aa] transition-colors hover:bg-white/5 lg:hidden" @click="emit('closeMobile')">
                <X class="h-4 w-4" />
            </button>
        </div>

        <!-- Composer -->
        <div class="px-3 pb-3">
            <div class="rounded-xl border border-[#2b2b2b] bg-[#252525] transition-colors focus-within:border-[#3a3a3a]">
                <input
                    v-model="composerValue"
                    type="text"
                    placeholder="Ask Claude to write code..."
                    class="w-full bg-transparent px-4 py-3 text-[13px] text-[#f3f4f6] outline-none placeholder:text-[#666666]"
                />
                <div class="flex items-center justify-between px-3 pb-3">
                    <div class="flex items-center gap-0.5">
                        <button class="flex h-8 w-8 items-center justify-center rounded-lg text-[#666666] transition-colors hover:bg-white/5 hover:text-[#888888]">
                            <Image class="h-[18px] w-[18px]" />
                        </button>
                        <button class="flex h-8 w-8 items-center justify-center rounded-lg text-[#666666] transition-colors hover:bg-white/5 hover:text-[#888888]">
                            <MoreHorizontal class="h-[18px] w-[18px]" />
                        </button>
                    </div>
                    <button
                        class="flex h-8 w-8 items-center justify-center rounded-full transition-all"
                        :class="composerValue ? 'bg-[#e07a5f] text-[#141414]' : 'bg-[#e07a5f] text-[#141414] opacity-60'"
                    >
                        <Send class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Repo/Branch bar -->
        <div class="flex items-center gap-1.5 border-b border-t border-[#2b2b2b] px-3 py-2.5">
            <!-- Repo selector -->
            <PopoverRoot v-model:open="repoOpen">
                <PopoverTrigger as-child>
                    <button class="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-[#a1a1aa] transition-colors hover:bg-white/5">
                        <Github class="h-4 w-4" />
                        <span class="text-[13px]">{{ selectedRepo()?.name }}</span>
                    </button>
                </PopoverTrigger>
                <PopoverPortal>
                    <PopoverContent :side-offset="8" align="start" class="w-[280px] rounded-xl border border-[#2b2b2b] bg-[#202020] shadow-2xl">
                        <div class="border-b border-[#2b2b2b] p-2">
                            <div class="flex items-center gap-2 rounded-lg bg-[#1b1b1b] px-3 py-2">
                                <Search class="h-4 w-4 text-[#666666]" />
                                <input v-model="repoSearchQuery" type="text" placeholder="Search repositories" class="flex-1 bg-transparent text-[13px] text-[#f3f4f6] outline-none placeholder:text-[#666666]" />
                            </div>
                        </div>
                        <div class="max-h-[280px] overflow-y-auto p-1.5">
                            <p class="px-2 py-1.5 text-[10px] font-medium uppercase tracking-wider text-[#666666]">Recently Used</p>
                            <button
                                v-for="repo in repos"
                                :key="repo.name"
                                class="flex w-full items-center justify-between rounded-lg px-2 py-2 transition-colors hover:bg-white/5"
                                @click="emit('selectRepo', repo.name); repoOpen = false"
                            >
                                <div class="flex flex-col items-start">
                                    <span class="text-[13px] font-medium text-[#f3f4f6]">{{ repo.name }}</span>
                                    <span class="text-[11px] text-[#666666]">{{ repo.owner }}</span>
                                </div>
                                <Check v-if="repo.selected" class="h-4 w-4 text-[#e07a5f]" />
                            </button>
                        </div>
                        <div class="border-t border-[#2b2b2b] p-2">
                            <p class="mb-2 px-2 text-[11px] text-[#666666]">Repo missing? Install the Claude GitHub app in a private repository to access it here.</p>
                            <button class="flex w-full items-center justify-center gap-2 rounded-lg bg-[#2b2b2b] py-2.5 text-[13px] font-medium text-[#f3f4f6] transition-colors hover:bg-[#333333]">
                                <Github class="h-4 w-4" />
                                Install GitHub App
                            </button>
                        </div>
                    </PopoverContent>
                </PopoverPortal>
            </PopoverRoot>

            <!-- Branch selector -->
            <PopoverRoot v-model:open="branchOpen">
                <PopoverTrigger as-child>
                    <button class="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-[#a1a1aa] transition-colors hover:bg-white/5">
                        <GitBranch class="h-4 w-4" />
                        <span class="text-[13px]">{{ selectedBranch()?.name }}</span>
                    </button>
                </PopoverTrigger>
                <PopoverPortal>
                    <PopoverContent :side-offset="8" align="start" class="w-[200px] rounded-xl border border-[#2b2b2b] bg-[#202020] shadow-2xl">
                        <div class="border-b border-[#2b2b2b] p-2">
                            <div class="flex items-center gap-2 rounded-lg bg-[#1b1b1b] px-3 py-2">
                                <Search class="h-4 w-4 text-[#666666]" />
                                <input v-model="branchSearchQuery" type="text" placeholder="Search branches" class="flex-1 bg-transparent text-[13px] text-[#f3f4f6] outline-none placeholder:text-[#666666]" />
                            </div>
                        </div>
                        <div class="max-h-[200px] overflow-y-auto p-1.5">
                            <button
                                v-for="branch in branches"
                                :key="branch.name"
                                class="flex w-full items-center justify-between rounded-lg px-2 py-2 transition-colors hover:bg-white/5"
                                @click="emit('selectBranch', branch.name); branchOpen = false"
                            >
                                <span class="text-[13px] text-[#f3f4f6]">{{ branch.name }}</span>
                                <Check v-if="branch.selected" class="h-4 w-4 text-[#e07a5f]" />
                            </button>
                        </div>
                    </PopoverContent>
                </PopoverPortal>
            </PopoverRoot>

            <!-- Default pill -->
            <div class="ml-auto flex items-center gap-1.5 rounded-full border border-[#2b2b2b] bg-[#202020] px-2.5 py-1">
                <RefreshCw class="h-3 w-3 text-[#666666]" />
                <span class="text-[11px] text-[#808080]">Default</span>
            </div>
        </div>

        <!-- Sessions header -->
        <div class="flex items-center justify-between px-4 py-3">
            <span class="text-[11px] font-medium uppercase tracking-wider text-[#666666]">Sessions</span>
            <DropdownMenuRoot>
                <DropdownMenuTrigger as-child>
                    <button class="flex h-6 w-6 items-center justify-center rounded text-[#666666] transition-colors hover:bg-white/5 hover:text-[#888888]">
                        <Filter class="h-3.5 w-3.5" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent :side-offset="4" align="end" class="min-w-[120px] rounded-lg border border-[#2b2b2b] bg-[#202020] p-1 shadow-xl">
                        <DropdownMenuItem
                            v-for="option in ['Active', 'Archived', 'All']"
                            :key="option"
                            class="flex cursor-pointer items-center justify-between rounded-md px-2.5 py-1.5 text-[13px] outline-none transition-colors hover:bg-white/5"
                            :class="filterOption === option ? 'text-[#f3f4f6]' : 'text-[#a1a1aa]'"
                            @click="filterOption = option"
                        >
                            {{ option }}
                            <Check v-if="filterOption === option" class="h-3.5 w-3.5 text-[#e07a5f]" />
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
        </div>

        <!-- Sessions list -->
        <div class="flex-1 overflow-y-auto px-2">
            <button
                v-for="session in sessions"
                :key="session.id"
                class="group mb-0.5 flex w-full flex-col rounded-lg px-3 py-2.5 text-left transition-all hover:bg-white/[0.03]"
                :class="session.active ? 'border-l-2 border-[#e07a5f] bg-[#202020]' : ''"
                @click="emit('selectSession', session.id)"
            >
                <div class="flex w-full items-start justify-between gap-2">
                    <span class="line-clamp-2 text-[13px] font-medium" :class="session.active ? 'text-[#f3f4f6]' : 'text-[#c4c4c4]'">
                        {{ session.title }}
                    </span>
                    <span v-if="session.active" class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-[#e07a5f]" />
                </div>
                <div class="mt-1.5 flex items-center gap-1.5">
                    <span class="text-[11px] text-[#666666]">{{ session.project }}</span>
                    <span class="text-[11px] text-[#666666]">·</span>
                    <span class="text-[11px] text-[#666666]">{{ session.time }}</span>
                    <template v-if="session.metrics">
                        <span class="text-[11px] text-[#666666]">·</span>
                        <span class="text-[11px] text-[#4ade80]">+{{ session.metrics.additions }}</span>
                        <span class="text-[11px] text-[#f87171]">-{{ session.metrics.deletions }}</span>
                        <GitBranch class="h-3 w-3 text-[#666666]" />
                    </template>
                </div>
            </button>
        </div>

        <!-- User menu -->
        <PopoverRoot>
            <PopoverTrigger as-child>
                <button class="flex w-full items-center gap-3 border-t border-[#2b2b2b] px-4 py-3 transition-colors hover:bg-white/[0.03]">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-[#e07a5f] text-[11px] font-semibold text-[#141414]">
                        {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                </button>
            </PopoverTrigger>
            <PopoverPortal>
                <PopoverContent side="top" :side-offset="8" align="start" class="w-[240px] rounded-xl border border-[#2b2b2b] bg-[#202020] p-2 shadow-2xl">
                    <div class="px-2 py-1.5">
                        <span class="text-[11px] text-[#666666]">{{ user.email }}</span>
                    </div>
                    <div class="my-1 border-b border-t border-[#2b2b2b] py-1">
                        <button
                            v-for="workspace in user.workspaces"
                            :key="workspace.name"
                            class="flex w-full items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-white/5"
                        >
                            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#2b2b2b]">
                                <Users v-if="workspace.plan === 'Team plan'" class="h-4 w-4 text-[#a1a1aa]" />
                                <User v-else class="h-4 w-4 text-[#a1a1aa]" />
                            </div>
                            <div class="flex flex-1 flex-col items-start">
                                <span class="text-[13px] font-medium text-[#f3f4f6]">{{ workspace.name }}</span>
                                <span class="text-[11px] text-[#666666]">{{ workspace.plan }}</span>
                            </div>
                            <Check v-if="workspace.selected" class="h-4 w-4 text-[#e07a5f]" />
                        </button>
                    </div>
                    <button class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-[#a1a1aa] transition-colors hover:bg-white/5">
                        <Settings class="h-4 w-4 text-[#666666]" />
                        <span class="flex-1 text-left text-[13px]">Settings</span>
                        <span class="text-[11px] text-[#666666]">⇧+Ctrl+,</span>
                    </button>
                    <button class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-[#a1a1aa] transition-colors hover:bg-white/5">
                        <Globe class="h-4 w-4 text-[#666666]" />
                        <span class="flex-1 text-left text-[13px]">Language</span>
                        <ChevronRight class="h-4 w-4 text-[#666666]" />
                    </button>
                    <button class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-[#a1a1aa] transition-colors hover:bg-white/5">
                        <HelpCircle class="h-4 w-4 text-[#666666]" />
                        <span class="flex-1 text-left text-[13px]">Get help</span>
                    </button>
                    <div class="my-1 border-t border-[#2b2b2b]" />
                    <button class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-[#a1a1aa] transition-colors hover:bg-white/5">
                        <CreditCard class="h-4 w-4 text-[#666666]" />
                        <span class="flex-1 text-left text-[13px]">View all plans</span>
                    </button>
                    <button class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-[#a1a1aa] transition-colors hover:bg-white/5">
                        <BookOpen class="h-4 w-4 text-[#666666]" />
                        <span class="flex-1 text-left text-[13px]">Learn more</span>
                        <ChevronRight class="h-4 w-4 text-[#666666]" />
                    </button>
                    <div class="my-1 border-t border-[#2b2b2b]" />
                    <button class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-[#a1a1aa] transition-colors hover:bg-white/5">
                        <LogOut class="h-4 w-4 text-[#666666]" />
                        <span class="flex-1 text-left text-[13px]">Log out</span>
                    </button>
                </PopoverContent>
            </PopoverPortal>
        </PopoverRoot>
    </aside>
</template>
