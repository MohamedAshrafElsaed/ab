<script setup lang="ts">
import { Circle } from 'lucide-vue-next';

interface ActivityItem {
    type: 'read' | 'write' | 'grep' | 'glob' | 'todo';
    path?: string;
    pattern?: string;
    lines?: number;
    items?: string[];
}

defineProps<{
    activity: ActivityItem[];
}>();

const getActionLabel = (type: string): string => {
    const labels: Record<string, string> = {
        read: 'Read',
        write: 'Write',
        grep: 'Grep',
        glob: 'Glob',
        todo: 'Update Todos',
    };
    return labels[type] || type;
};
</script>

<template>
    <div class="space-y-3">
        <div v-for="(item, index) in activity" :key="index" class="flex items-start gap-3">
            <!-- Bullet indicator -->
            <div class="mt-1.5 flex-shrink-0">
                <div class="h-2 w-2 rounded-full" style="background-color: #22c55e" />
            </div>

            <!-- Content -->
            <div class="min-w-0 flex-1">
                <!-- Todo items -->
                <template v-if="item.type === 'todo'">
                    <span class="text-sm font-medium" style="color: #f3f4f6">{{ getActionLabel(item.type) }}</span>
                    <div v-if="item.items && item.items.length > 0" class="mt-2 space-y-1 pl-4">
                        <div v-for="(todoItem, todoIndex) in item.items" :key="todoIndex" class="flex items-center gap-2">
                            <Circle class="h-3 w-3 flex-shrink-0" style="color: #71717a" />
                            <span class="text-sm" style="color: #a1a1aa">{{ todoItem }}</span>
                        </div>
                    </div>
                </template>

                <!-- Read/Write/Grep/Glob actions -->
                <template v-else>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium" style="color: #f3f4f6">{{ getActionLabel(item.type) }}</span>
                        <code
                            v-if="item.path"
                            class="rounded px-1.5 py-0.5 font-mono text-xs break-all"
                            style="background-color: #202020; color: #a1a1aa"
                        >
                            {{ item.path }}
                        </code>
                        <code v-if="item.pattern" class="rounded px-1.5 py-0.5 font-mono text-xs" style="background-color: #202020; color: #a1a1aa">
                            {{ item.pattern }}
                        </code>
                    </div>
                    <div v-if="item.lines" class="mt-1 pl-4">
                        <span class="text-xs" style="color: #71717a">Read {{ item.lines }} lines</span>
                    </div>
                </template>
            </div>
        </div>

        <!-- Additional message if present -->
        <div v-if="activity.some((a) => a.type === 'write')" class="mt-4 flex items-start gap-3">
            <div class="mt-1.5 flex-shrink-0">
                <div class="h-2 w-2 rounded-full" style="background-color: #22c55e" />
            </div>
            <div class="flex-1">
                <p class="text-sm" style="color: #f3f4f6">
                    Now I'll create a custom VerifyEmail notification that uses the
                    <code class="rounded px-1.5 py-0.5 font-mono text-xs" style="background-color: #202020; color: #e07a5f"> emails </code>
                    queue:
                </p>
            </div>
        </div>
    </div>
</template>
