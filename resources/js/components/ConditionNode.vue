<script setup>
import { computed } from 'vue';
import { t } from '../i18n';

const props = defineProps({
    node: { type: Object, required: true },
    depth: { type: Number, default: 0 },
    root: { type: Boolean, default: false },
});

const emit = defineEmits(['update', 'remove']);

// Values are the wire format and never change; only the labels are translated.
const SOURCE_VALUES = ['json', 'header', 'query', 'meta', 'body'];

const OPERATOR_VALUES = [
    'equals', 'not_equals', 'contains', 'not_contains', 'starts_with', 'ends_with',
    'regex', 'not_regex', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in',
    'exists', 'not_exists', 'is_empty', 'is_not_empty', 'is_true', 'is_false',
];

const SOURCES = computed(() => SOURCE_VALUES.map((value) => ({ value, label: t(`source.${value}`) })));
const OPERATORS = computed(() => OPERATOR_VALUES.map((value) => ({ value, label: t(`operator.${value}`) })));

const VALUELESS = ['exists', 'not_exists', 'is_empty', 'is_not_empty', 'is_true', 'is_false'];

const isGroup = computed(() => (props.node.type ?? 'group') === 'group');
const needsValue = computed(() => !VALUELESS.includes(props.node.operator));

const patch = (changes) => emit('update', { ...props.node, ...changes });

const patchChild = (index, child) => {
    const children = [...props.node.children];
    children[index] = child;
    patch({ children });
};

const removeChild = (index) => patch({ children: props.node.children.filter((_, i) => i !== index) });

const addCondition = () =>
    patch({
        children: [
            ...(props.node.children ?? []),
            { type: 'cond', source: 'json', path: '', operator: 'equals', value: '', ci: false },
        ],
    });

const addGroup = () =>
    patch({
        children: [...(props.node.children ?? []), { type: 'group', op: 'or', children: [] }],
    });

const placeholder = computed(() =>
    SOURCE_VALUES.includes(props.node.source) ? t(`sourceHint.${props.node.source}`) : '',
);
</script>

<template>
    <!-- Group: AND/OR across the child conditions -->
    <div v-if="isGroup" class="rounded-lg border border-slate-200 bg-slate-50 p-2 dark:bg-slate-800/40 dark:border-slate-800">
        <div class="mb-2 flex items-center gap-2 text-xs">
            <span class="text-slate-500 dark:text-slate-400">{{ $t('cond.match') }}</span>
            <select
                :value="node.op"
                class="rounded border border-slate-300 bg-white px-1.5 py-0.5 dark:bg-slate-900 dark:border-slate-700"
                @change="patch({ op: $event.target.value })"
            >
                <option value="and">{{ $t('cond.all') }}</option>
                <option value="or">{{ $t('cond.any') }}</option>
            </select>
            <span class="text-slate-500 dark:text-slate-400">{{ $t('cond.ofTheFollowing') }}</span>

            <label class="ml-2 flex items-center gap-1 text-slate-500 dark:text-slate-400">
                <input
                    type="checkbox"
                    :checked="!!node.not"
                    class="rounded border-slate-300 dark:border-slate-700"
                    @change="patch({ not: $event.target.checked })"
                />
                {{ $t('cond.negated') }}
            </label>

            <div class="ml-auto flex gap-1">
                <button class="rounded px-1.5 py-0.5 text-blue-600 hover:bg-blue-50 dark:text-blue-400" @click="addCondition">
                    {{ $t('cond.addCondition') }}
                </button>
                <button class="rounded px-1.5 py-0.5 text-slate-500 hover:bg-slate-200 dark:text-slate-400 dark:hover:bg-slate-700" @click="addGroup">
                    {{ $t('cond.addGroup') }}
                </button>
                <button v-if="!root" class="rounded px-1.5 py-0.5 text-red-500 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/50" @click="emit('remove')">
                    ✕
                </button>
            </div>
        </div>

        <p v-if="!node.children?.length" class="px-1 py-2 text-xs text-slate-400 dark:text-slate-500">
            {{ $t('cond.empty') }}
        </p>

        <div class="space-y-1.5">
            <ConditionNode
                v-for="(child, index) in node.children"
                :key="index"
                :node="child"
                :depth="depth + 1"
                @update="patchChild(index, $event)"
                @remove="removeChild(index)"
            />
        </div>
    </div>

    <!-- A single condition -->
    <div v-else class="flex flex-wrap items-center gap-1.5 rounded-lg border border-slate-200 bg-white p-2 text-xs dark:bg-slate-900 dark:border-slate-800">
        <select
            :value="node.source"
            class="rounded border border-slate-300 px-1.5 py-1 dark:border-slate-700"
            @change="patch({ source: $event.target.value })"
        >
            <option v-for="source in SOURCES" :key="source.value" :value="source.value">{{ source.label }}</option>
        </select>

        <input
            v-if="node.source !== 'body'"
            :value="node.path"
            :placeholder="placeholder"
            class="w-52 rounded border border-slate-300 px-2 py-1 font-mono dark:border-slate-700"
            @input="patch({ path: $event.target.value })"
        />

        <select
            :value="node.operator"
            class="rounded border border-slate-300 px-1.5 py-1 dark:border-slate-700"
            @change="patch({ operator: $event.target.value })"
        >
            <option v-for="operator in OPERATORS" :key="operator.value" :value="operator.value">
                {{ operator.label }}
            </option>
        </select>

        <input
            v-if="needsValue"
            :value="node.value"
            :placeholder="$t('cond.value')"
            class="min-w-0 flex-1 rounded border border-slate-300 px-2 py-1 font-mono dark:border-slate-700"
            @input="patch({ value: $event.target.value })"
        />

        <label v-if="needsValue" class="flex items-center gap-1 text-slate-500 dark:text-slate-400" :title="$t('cond.caseInsensitive')">
            <input
                type="checkbox"
                :checked="!!node.ci"
                class="rounded border-slate-300 dark:border-slate-700"
                @change="patch({ ci: $event.target.checked })"
            />
            Aa
        </label>

        <button class="rounded px-1.5 py-1 text-red-500 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/50" @click="emit('remove')">✕</button>
    </div>
</template>
