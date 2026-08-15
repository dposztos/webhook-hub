<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    wide: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);
</script>

<template>
    <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-6 dark:bg-slate-950/70" @click.self="emit('close')">
        <div
            class="flex max-h-full w-full flex-col overflow-hidden rounded-xl bg-white shadow-xl dark:bg-slate-900"
            :class="wide ? 'max-w-5xl' : 'max-w-xl'"
        >
            <header class="flex items-start gap-3 border-b border-slate-200 px-5 py-3 dark:border-slate-800">
                <div class="min-w-0">
                    <h2 class="truncate font-semibold text-slate-900 dark:text-slate-100">{{ title }}</h2>
                    <p v-if="subtitle" class="truncate text-xs text-slate-500 dark:text-slate-400">{{ subtitle }}</p>
                </div>
                <button class="ml-auto shrink-0 rounded px-2 py-1 text-slate-400 hover:bg-slate-100 dark:text-slate-500 dark:hover:bg-slate-800" @click="emit('close')">
                    ✕
                </button>
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                <slot />
            </div>

            <footer v-if="$slots.footer" class="flex items-center gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3 dark:bg-slate-800/40 dark:border-slate-800">
                <slot name="footer" />
            </footer>
        </div>
    </div>
</template>
