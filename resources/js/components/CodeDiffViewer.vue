<script setup lang="ts">
import { ExternalLink } from 'lucide-vue-next';

interface DiffLine {
    type: 'add' | 'remove' | 'neutral';
    lineNumber: number;
    code: string;
}

defineProps<{
    lines: DiffLine[];
    remainingLines?: number;
}>();

const getLinePrefix = (type: string): string => {
    if (type === 'add') return '+';
    if (type === 'remove') return '-';
    return ' ';
};

const getLineColor = (type: string): string => {
    if (type === 'add') return '#22c55e';
    if (type === 'remove') return '#ef4444';
    return '#a1a1aa';
};

const getLineBgColor = (type: string): string => {
    if (type === 'add') return 'rgba(34, 197, 94, 0.1)';
    if (type === 'remove') return 'rgba(239, 68, 68, 0.1)';
    return 'transparent';
};
</script>

<template>
    <div class="flex items-start gap-3">
        <!-- Bullet indicator -->
        <div class="mt-1.5 flex-shrink-0">
            <div class="h-2 w-2 rounded-full" style="background-color: #22c55e" />
        </div>

        <!-- Code diff card -->
        <div class="min-w-0 flex-1">
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium" style="color: #f3f4f6">Write</span>
                <code class="rounded px-1.5 py-0.5 font-mono text-xs break-all" style="background-color: #202020; color: #a1a1aa">
                    /home/user/ConvertedOrders/app/Notifications/User/Auth/VerifyEmailNotification.php
                </code>
            </div>

            <div class="overflow-hidden rounded-lg border" style="background-color: #1b1b1b; border-color: #2b2b2b">
                <!-- Diff content -->
                <div class="overflow-x-auto">
                    <table class="w-full font-mono text-xs">
                        <tbody>
                            <tr v-for="line in lines" :key="line.lineNumber" :style="{ backgroundColor: getLineBgColor(line.type) }">
                                <!-- Line number -->
                                <td class="w-12 border-r px-2 py-0.5 text-right select-none" style="color: #71717a; border-color: #2b2b2b">
                                    {{ line.lineNumber }}
                                </td>
                                <!-- Diff indicator -->
                                <td class="w-6 px-1 py-0.5 text-center select-none" :style="{ color: getLineColor(line.type) }">
                                    {{ getLinePrefix(line.type) }}
                                </td>
                                <!-- Code content -->
                                <td class="px-2 py-0.5 whitespace-pre" :style="{ color: getLineColor(line.type) }">
                                    {{ line.code }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Show more link -->
                <div v-if="remainingLines && remainingLines > 0" class="border-t px-3 py-2" style="border-color: #2b2b2b">
                    <button class="text-xs transition-colors hover:underline" style="color: #71717a">
                        Show full diff ({{ remainingLines }} more lines)
                    </button>
                </div>
            </div>

            <!-- View PR button -->
            <div class="mt-3 flex justify-end">
                <button class="flex items-center gap-2 rounded-md px-3 py-1.5 transition-colors hover:opacity-80" style="background-color: #e07a5f">
                    <span class="text-sm font-medium" style="color: #141414">View PR</span>
                    <ExternalLink class="h-3.5 w-3.5" style="color: #141414" />
                </button>
            </div>
        </div>
    </div>
</template>
