<script setup lang="ts">
import { BookOpen, Check, ChevronRight, CreditCard, Globe, HelpCircle, LogOut, Settings, User as UserIcon, Users } from 'lucide-vue-next';
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui';

interface Workspace {
    name: string;
    plan: string;
    selected: boolean;
}

interface User {
    email: string;
    name: string;
    avatar: string | null;
    workspaces: Workspace[];
}

defineProps<{
    user: User;
}>();

const menuItems = [
    { icon: Settings, label: 'Settings', shortcut: '⇧ +Ctrl+,' },
    { icon: Globe, label: 'Language', hasArrow: true },
    { icon: HelpCircle, label: 'Get help' },
    { divider: true },
    { icon: CreditCard, label: 'View all plans' },
    { icon: BookOpen, label: 'Learn more', hasArrow: true },
    { divider: true },
    { icon: LogOut, label: 'Log out' },
];
</script>

<template>
    <PopoverRoot>
        <PopoverTrigger as-child>
            <button class="flex w-full items-center gap-3 border-t px-4 py-3 transition-colors hover:bg-white/5" style="border-color: #2b2b2b">
                <div
                    class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-medium"
                    style="background-color: #e07a5f; color: #141414"
                >
                    {{ user.name.charAt(0).toUpperCase() }}
                </div>
                <span class="text-sm" style="color: #a1a1aa">{{ user.email }}</span>
            </button>
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent
                side="top"
                :side-offset="8"
                align="start"
                class="w-[240px] rounded-xl border p-2 shadow-2xl"
                style="background-color: #202020; border-color: #2b2b2b"
            >
                <!-- User email header -->
                <div class="px-2 py-1.5">
                    <span class="text-xs" style="color: #71717a">{{ user.email }}</span>
                </div>

                <!-- Workspace switcher -->
                <div class="my-1 border-t border-b py-1" style="border-color: #2b2b2b">
                    <button
                        v-for="workspace in user.workspaces"
                        :key="workspace.name"
                        class="flex w-full items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-white/5"
                    >
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg" style="background-color: #2b2b2b">
                            <Users v-if="workspace.plan === 'Team plan'" class="h-4 w-4" style="color: #a1a1aa" />
                            <UserIcon v-else class="h-4 w-4" style="color: #a1a1aa" />
                        </div>
                        <div class="flex flex-1 flex-col items-start">
                            <span class="text-sm font-medium" style="color: #f3f4f6">{{ workspace.name }}</span>
                            <span class="text-xs" style="color: #71717a">{{ workspace.plan }}</span>
                        </div>
                        <Check v-if="workspace.selected" class="h-4 w-4" style="color: #e07a5f" />
                    </button>
                </div>

                <!-- Menu items -->
                <template v-for="(item, index) in menuItems" :key="index">
                    <div v-if="item.divider" class="my-1 border-t" style="border-color: #2b2b2b" />
                    <button v-else class="flex w-full items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-white/5">
                        <component :is="item.icon" class="h-4 w-4" style="color: #71717a" />
                        <span class="flex-1 text-left text-sm" style="color: #a1a1aa">{{ item.label }}</span>
                        <span v-if="item.shortcut" class="text-xs" style="color: #71717a">{{ item.shortcut }}</span>
                        <ChevronRight v-if="item.hasArrow" class="h-4 w-4" style="color: #71717a" />
                    </button>
                </template>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
