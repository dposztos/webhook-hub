<script setup>
import { computed, ref, watch } from 'vue';
import { api } from '../api';
import { copyText, formatSize, formatTime, methodColor } from '../format';
import JsonView from './JsonView.vue';
import { TOOLS, buildSnippet, targetUrl, usefulHeaders } from '../snippets';
import { t } from '../i18n';

const props = defineProps({
    detail: { type: Object, default: null },
    width: { type: Number, default: 608 },
});

const emit = defineEmits(['close', 'changed', 'notify', 'make-rule']);

const tab = ref('json');

watch(
    () => props.detail?.uuid,
    () => {
        tab.value = props.detail?.body_json ? 'json' : 'raw';
    },
);

const headerRows = computed(() => Object.entries(props.detail?.headers ?? {}));
const queryRows = computed(() => Object.entries(props.detail?.query ?? {}));

const copy = async (text, label) => {
    await copyText(text);
    emit('notify', t('common.copiedTo', { what: label }), 'success');
};

// What clicking a field in the JSON tree copies: a template reference or the value.
const pickMode = ref('ref');

const onPick = async ({ path, value }) => {
    if (pickMode.value === 'ref') {
        await copy(`{{ ${path} }}`, t('detail.reference', { path }));
        return;
    }

    const text = value !== null && typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value);
    await copy(text, t('detail.valueOf', { path }));
};

const prettyJson = computed(() =>
    props.detail?.body_json ? JSON.stringify(props.detail.body_json, null, 2) : '',
);

// "Send" tab: the same request replayed with the usual tools
const tool = ref('curl');
const allHeaders = ref(false);

const snippet = computed(() =>
    props.detail ? buildSnippet(tool.value, props.detail, { allHeaders: allHeaders.value }) : '',
);

const droppedHeaders = computed(() => {
    if (!props.detail) return 0;
    return usefulHeaders(props.detail, true).length - usefulHeaders(props.detail, false).length;
});

const sendTarget = computed(() => (props.detail ? targetUrl(props.detail) : ''));

const replay = async () => {
    try {
        await api.replayMessage(props.detail.uuid);
        emit('notify', t('detail.replayed'), 'success');
        setTimeout(() => emit('changed'), 1500);
    } catch (error) {
        emit('notify', error.message, 'error');
    }
};

const markUnread = async () => {
    try {
        await api.markUnread(props.detail.uuid);
        emit('notify', t('detail.markedUnread'), 'success');
        emit('close');
        emit('changed');
    } catch (error) {
        emit('notify', error.message, 'error');
    }
};

const remove = async () => {
    if (!window.confirm(t('detail.confirmDelete'))) return;

    try {
        await api.deleteMessage(props.detail.uuid);
        emit('notify', t('detail.deleted'), 'success');
        emit('close');
        emit('changed');
    } catch (error) {
        emit('notify', error.message, 'error');
    }
};

const statusClass = (status) =>
    ({
        success: 'bg-emerald-50 text-emerald-700 ring-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-300 dark:ring-emerald-900',
        failed: 'bg-red-50 text-red-700 ring-red-100 dark:bg-red-950/50 dark:text-red-300 dark:ring-red-900',
        skipped: 'bg-slate-50 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
    })[status] ?? 'bg-slate-50 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700';
</script>

<template>
    <section
        v-if="detail"
        class="flex shrink-0 flex-col bg-white dark:bg-slate-900"
        :style="{ width: `${width}px` }"
    >
        <div class="flex items-start gap-2 border-b border-slate-200 px-4 py-2.5 dark:border-slate-800">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="rounded px-1.5 py-0.5 text-xs font-semibold" :class="methodColor(detail.method)">
                        {{ detail.method }}
                    </span>
                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ formatTime(detail.created_at) }}</span>
                    <span class="text-xs text-slate-400 dark:text-slate-500">{{ formatSize(detail.size) }}</span>
                    <span v-if="detail.truncated" class="text-xs text-amber-600 dark:text-amber-400" :title="$t('detail.truncatedTitle')">
                        {{ $t('detail.truncated') }}
                    </span>
                </div>
                <p class="mt-0.5 truncate text-xs text-slate-400 dark:text-slate-500">
                    {{ detail.ip }} · {{ detail.content_type || $t('detail.noContentType') }}
                </p>
            </div>

            <div class="ml-auto flex shrink-0 items-center gap-1 text-xs">
                <button class="btn-ghost" :title="$t('detail.replayTitle')" @click="replay">{{ $t('detail.replay') }}</button>
                <button class="btn-ghost" :title="$t('detail.markUnreadTitle')" @click="markUnread">{{ $t('detail.markUnread') }}</button>
                <button class="btn-ghost text-red-600 dark:text-red-400" @click="remove">{{ $t('common.delete') }}</button>
                <button class="btn-ghost" @click="emit('close')">✕</button>
            </div>
        </div>

        <nav class="flex gap-1 border-b border-slate-200 px-3 pt-2 text-sm dark:border-slate-800">
            <button v-for="item in [
                    { key: 'json', label: 'JSON', show: !!detail.body_json },
                    { key: 'raw', label: $t('detail.tabRaw'), show: true },
                    { key: 'headers', label: $t('detail.tabHeaders', { count: headerRows.length }), show: true },
                    { key: 'send', label: $t('detail.tabSend'), show: true },
                    { key: 'runs', label: $t('detail.tabRuns', { count: detail.runs?.length ?? 0 }), show: true },
                ].filter((item) => item.show)"
                :key="item.key"
                class="rounded-t-lg px-3 py-1.5"
                :class="tab === item.key ? 'bg-slate-100 font-medium text-slate-900 dark:bg-slate-800 dark:text-slate-100' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'"
                @click="tab = item.key"
            >
                {{ item.label }}
            </button>
        </nav>

        <div class="min-h-0 flex-1 overflow-y-auto p-4">
            <template v-if="tab === 'json'">
                <div class="mb-3 flex flex-wrap items-center gap-2 rounded-lg bg-slate-50 p-2 text-xs dark:bg-slate-800">
                    <button class="btn-secondary text-xs" @click="copy(prettyJson, $t('detail.prettyJson'))">
                        {{ $t('detail.copyPretty') }}
                    </button>

                    <span class="ml-auto text-slate-500 dark:text-slate-400">{{ $t('detail.onClick') }}</span>
                    <div class="flex overflow-hidden rounded-lg border border-slate-300 dark:border-slate-700">
                        <button
                            v-for="option in [
                                { key: 'ref', label: $t('detail.pickReference') },
                                { key: 'value', label: $t('detail.pickValue') },
                            ]"
                            :key="option.key"
                            class="px-2 py-1"
                            :class="pickMode === option.key
                                ? 'bg-blue-600 text-white'
                                : 'bg-white text-slate-600 hover:bg-slate-100 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'"
                            @click="pickMode = option.key"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>

                <JsonView :value="detail.body_json" path="json" @pick="onPick" />
            </template>

            <template v-else-if="tab === 'raw'">
                <div class="mb-2 flex justify-end">
                    <button class="btn-ghost text-xs" @click="copy(detail.body, $t('detail.tabRaw'))">{{ $t('common.copy') }}</button>
                </div>
                <pre class="overflow-x-auto whitespace-pre-wrap break-all rounded bg-slate-50 p-3 font-mono text-xs text-slate-700 dark:bg-slate-900 dark:text-slate-300">{{ detail.body || $t('detail.emptyBody') }}</pre>
            </template>

            <template v-else-if="tab === 'headers'">
                <table class="w-full text-left text-xs">
                    <tbody>
                        <tr v-for="[name, value] in headerRows" :key="name" class="border-b border-slate-100 align-top dark:border-slate-800">
                            <th class="w-48 py-1 pr-3 font-medium text-slate-500 dark:text-slate-400">{{ name }}</th>
                            <td class="break-all py-1 font-mono text-slate-700 dark:text-slate-300">{{ value }}</td>
                        </tr>
                    </tbody>
                </table>

                <template v-if="queryRows.length">
                    <h3 class="mb-1 mt-4 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t('detail.queryParams') }}</h3>
                    <table class="w-full text-left text-xs">
                        <tbody>
                            <tr v-for="[name, value] in queryRows" :key="name" class="border-b border-slate-100 dark:border-slate-800">
                                <th class="w-48 py-1 pr-3 font-medium text-slate-500 dark:text-slate-400">{{ name }}</th>
                                <td class="break-all py-1 font-mono text-slate-700 dark:text-slate-300">{{ value }}</td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </template>

            <template v-else-if="tab === 'send'">
                <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">{{ $t('send.intro') }}</p>

                <div class="mb-3 flex flex-wrap gap-1">
                    <button
                        v-for="option in TOOLS"
                        :key="option.key"
                        class="rounded-lg px-2.5 py-1 text-xs"
                        :class="tool === option.key
                            ? 'bg-blue-600 text-white'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                        @click="tool = option.key"
                    >
                        {{ option.labelKey ? $t(option.labelKey) : option.label }}
                    </button>
                </div>

                <div class="mb-2 flex items-center gap-3 text-xs">
                    <label class="flex items-center gap-1.5 text-slate-600 dark:text-slate-400">
                        <input v-model="allHeaders" type="checkbox" class="rounded border-slate-300 dark:border-slate-700" />
                        {{ $t('send.allHeaders') }}
                        <span v-if="!allHeaders && droppedHeaders" class="text-slate-400 dark:text-slate-500">
                            {{ $t('send.droppedHeaders', { count: droppedHeaders }) }}
                        </span>
                    </label>

                    <button class="btn-secondary ml-auto text-xs" @click="copy(snippet, $t('send.command'))">{{ $t('common.copy') }}</button>
                </div>

                <pre class="overflow-x-auto rounded-lg bg-slate-900 p-3 font-mono text-xs leading-relaxed text-slate-100 dark:bg-slate-950">{{ snippet }}</pre>

                <p v-if="detail.truncated" class="mt-2 text-xs text-amber-600 dark:text-amber-400">{{ $t('send.truncatedWarning') }}</p>

                <p class="hint break-all">{{ $t('send.target', { url: sendTarget }) }}</p>
            </template>

            <template v-else>
                <p v-if="!detail.runs?.length" class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">
                    {{ $t('runs.none') }}<br />
                    <button class="mt-2 text-blue-600 hover:underline dark:text-blue-400" @click="emit('make-rule')">
                        {{ $t('runs.createRule') }}
                    </button>
                </p>

                <div v-for="run in detail.runs" :key="run.id" class="mb-2 rounded-lg p-3 ring-1" :class="statusClass(run.status)">
                    <div class="flex items-center gap-2 text-xs">
                        <strong>{{ run.rule ?? $t('runs.deletedRule') }}</strong>
                        <span class="opacity-70">· {{ run.type }} · {{ run.duration_ms }} ms</span>
                        <span class="ml-auto">{{ formatTime(run.created_at) }}</span>
                    </div>
                    <p class="mt-1 text-sm">{{ run.error ?? run.summary }}</p>
                    <p v-if="run.detail?.subject" class="mt-1 text-xs opacity-80">
                        {{ $t('runs.subject', { subject: run.detail.subject }) }}
                    </p>
                </div>
            </template>
        </div>
    </section>
</template>

