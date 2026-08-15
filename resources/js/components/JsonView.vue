<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    value: { required: true },
    path: { type: String, default: 'json' },
    depth: { type: Number, default: 0 },
});

const emit = defineEmits(['pick']);

const open = ref(props.depth < 3);

const kind = computed(() => {
    if (Array.isArray(props.value)) return 'array';
    if (props.value === null) return 'null';
    return typeof props.value;
});

const isLeaf = computed(() => kind.value !== 'object' && kind.value !== 'array');
const entries = computed(() => (isLeaf.value ? [] : Object.entries(props.value)));

const summary = computed(() =>
    kind.value === 'array' ? `[${entries.value.length} elem]` : `{${entries.value.length} mező}`,
);

const leafClass = computed(
    () =>
        ({
            string: 'text-emerald-700 dark:text-emerald-400',
            number: 'text-blue-700 dark:text-blue-400',
            boolean: 'text-purple-700 dark:text-purple-400',
            null: 'text-slate-400 dark:text-slate-500',
        })[kind.value] ?? 'text-slate-700 dark:text-slate-300',
);

const leafText = computed(() => {
    if (kind.value === 'string') return `"${props.value}"`;
    if (kind.value === 'null') return 'null';
    return String(props.value);
});
</script>

<template>
    <div class="font-mono text-xs leading-relaxed">
        <template v-if="isLeaf">
            <span :class="leafClass">{{ leafText }}</span>
        </template>

        <template v-else>
            <button class="text-slate-400 hover:text-slate-700 dark:text-slate-500 dark:hover:text-slate-200" @click="open = !open">
                {{ open ? '▾' : '▸' }} <span class="text-slate-400 dark:text-slate-500">{{ summary }}</span>
            </button>

            <div v-if="open" class="border-l border-slate-200 pl-3 dark:border-slate-800">
                <div v-for="[key, child] in entries" :key="key" class="flex gap-1.5">
                    <button
                        class="shrink-0 text-slate-500 hover:text-blue-600 hover:underline dark:text-slate-400"
                        :title="`${path}.${key}`"
                        @click="emit('pick', { path: `${path}.${key}`, value: child })"
                    >
                        {{ key }}:
                    </button>
                    <JsonView
                        :value="child"
                        :path="`${path}.${key}`"
                        :depth="depth + 1"
                        class="min-w-0 flex-1"
                        @pick="emit('pick', $event)"
                    />
                </div>
            </div>
        </template>
    </div>
</template>
