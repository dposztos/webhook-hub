<script setup>
import { computed } from 'vue';
import { api } from '../api';
import { copyText, formatSize, formatTime, methodColor } from '../format';
import { t } from '../i18n';

const props = defineProps({
    messages: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
    selection: { type: Object, default: null },
    activeUuid: { type: String, default: null },
    filters: { type: Object, required: true },
});

const emit = defineEmits(['open', 'rules', 'settings', 'changed', 'notify', 'update:filters']);

const setFilter = (key, value) => emit('update:filters', { ...props.filters, [key]: value });

const isEndpoint = computed(() => props.selection?.type === 'endpoint');

const copyUrl = async () => {
    await copyText(props.selection.url);
    emit('notify', t('tree.urlCopied'), 'success');
};

const unreadCount = computed(() => props.selection?.unread_count ?? 0);

const markAllRead = async () => {
    try {
        const result = await api.markAllRead(
            props.selection.type === 'endpoint'
                ? { endpoint_id: props.selection.id }
                : { group_id: props.selection.id },
        );
        emit('notify', t('list.markedRead', { count: result.marked }), 'success');
        emit('changed');
    } catch (error) {
        emit('notify', error.message, 'error');
    }
};

const clearMessages = async () => {
    if (!window.confirm(t('list.confirmClear', { name: props.selection.name }))) return;

    try {
        const result = await api.clearMessages(props.selection.id);
        emit('notify', t('list.cleared', { count: result.deleted }), 'success');
        emit('changed');
    } catch (error) {
        emit('notify', error.message, 'error');
    }
};
</script>

<template>
    <section class="flex min-w-0 flex-1 flex-col border-r border-slate-200 bg-slate-50 dark:bg-slate-900 dark:border-slate-800">
        <div v-if="selection" class="border-b border-slate-200 bg-white px-4 py-2.5 dark:bg-slate-900 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <h1 class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">
                    {{ selection.name }}
                    <span v-if="!isEndpoint" class="ml-1 font-normal text-slate-400 dark:text-slate-500">{{ $t('list.groupScope') }}</span>
                </h1>

                <div class="ml-auto flex shrink-0 items-center gap-2 text-xs">
                    <button v-if="unreadCount" class="btn-ghost text-blue-600 dark:text-blue-400" @click="markAllRead">
                        {{ $t('list.markAllRead', { count: unreadCount }) }}
                    </button>
                    <button class="btn-ghost" @click="emit('rules')">{{ $t('list.rules') }}</button>
                    <button v-if="isEndpoint" class="btn-ghost" @click="emit('settings')">{{ $t('list.settings') }}</button>
                    <button v-if="isEndpoint" class="btn-ghost text-red-600 dark:text-red-400" @click="clearMessages">{{ $t('list.clear') }}</button>
                </div>
            </div>

            <div v-if="isEndpoint && selection.url" class="mt-1.5 flex items-center gap-2">
                <code class="min-w-0 flex-1 truncate rounded bg-slate-100 px-2 py-1 font-mono text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                    {{ selection.url }}
                </code>
                <button class="btn-ghost shrink-0" @click="copyUrl">{{ $t('common.copy') }}</button>
            </div>
        </div>

        <div class="flex items-center gap-2 border-b border-slate-200 bg-white px-4 py-2 dark:bg-slate-900 dark:border-slate-800">
            <input
                :value="filters.q"
                :placeholder="$t('list.searchPlaceholder')"
                class="min-w-0 flex-1 rounded-lg border border-slate-300 px-2.5 py-1 text-sm outline-none focus:border-blue-500 dark:border-slate-700"
                @input="setFilter('q', $event.target.value)"
            />
            <select
                :value="filters.method"
                class="rounded-lg border border-slate-300 px-2 py-1 text-sm dark:border-slate-700"
                @change="setFilter('method', $event.target.value)"
            >
                <option value="">{{ $t('list.allMethods') }}</option>
                <option v-for="m in ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']" :key="m" :value="m">{{ m }}</option>
            </select>
            <select
                :value="filters.only"
                class="rounded-lg border border-slate-300 px-2 py-1 text-sm dark:border-slate-700"
                @change="setFilter('only', $event.target.value)"
            >
                <option value="">{{ $t('list.filterAll') }}</option>
                <option value="unread">{{ $t('list.filterUnread') }}</option>
                <option value="matched">{{ $t('list.filterMatched') }}</option>
                <option value="failed">{{ $t('list.filterFailed') }}</option>
                <option value="unprocessed">{{ $t('list.filterPending') }}</option>
            </select>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
            <p v-if="loading && !messages.length" class="p-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ $t('common.loading') }}</p>

            <p v-else-if="!messages.length" class="p-8 text-center text-sm text-slate-400 dark:text-slate-500">
                {{ $t('list.empty') }}<br />
                <span v-if="isEndpoint">{{ $t('list.emptyHint') }}</span>
            </p>

            <button
                v-for="message in messages"
                :key="message.uuid"
                class="block w-full border-b border-l-2 border-slate-200 px-4 py-2.5 text-left hover:bg-white dark:border-slate-800 dark:hover:bg-slate-800"
                :class="[
                    message.uuid === activeUuid ? 'bg-white ring-1 ring-inset ring-blue-200 dark:bg-slate-800 dark:ring-blue-800' : '',
                    message.read ? 'border-l-transparent' : 'border-l-blue-500 bg-blue-50/40 dark:bg-blue-950/20',
                ]"
                @click="emit('open', message.uuid)"
            >
                <div class="flex items-center gap-2 text-xs">
                    <span class="rounded px-1.5 py-0.5 font-semibold" :class="methodColor(message.method)">
                        {{ message.method }}
                    </span>
                    <span
                        class="text-slate-500 dark:text-slate-400"
                        :class="{ 'font-semibold text-slate-900 dark:text-slate-100': !message.read }"
                    >
                        {{ formatTime(message.created_at) }}
                    </span>
                    <span
                        v-if="!message.read"
                        class="h-1.5 w-1.5 shrink-0 rounded-full bg-blue-500"
                        :title="$t('list.unread')"
                    ></span>
                    <span v-if="!isEndpoint" class="truncate text-slate-400 dark:text-slate-500">· {{ message.endpoint.name }}</span>
                    <span class="ml-auto shrink-0 text-slate-400 dark:text-slate-500">{{ formatSize(message.size) }}</span>
                </div>

                <p
                    class="mt-1 truncate font-mono text-xs text-slate-600 dark:text-slate-400"
                    :class="{ 'text-slate-900 dark:text-slate-200': !message.read }"
                >
                    {{ message.preview || '—' }}
                </p>

                <div v-if="message.matched_rules?.length || message.actions_failed" class="mt-1 flex flex-wrap gap-1">
                    <span
                        v-for="rule in message.matched_rules"
                        :key="rule.id"
                        class="rounded bg-emerald-50 px-1.5 py-0.5 text-[11px] text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                    >
                        ⚡ {{ rule.name }}
                    </span>
                    <span
                        v-if="message.actions_failed"
                        class="rounded bg-red-50 px-1.5 py-0.5 text-[11px] text-red-700 dark:bg-red-950 dark:text-red-300"
                    >
                        {{ $t('list.actionsFailed', { count: message.actions_failed }) }}
                    </span>
                    <span
                        v-else-if="message.actions_ok"
                        class="rounded bg-blue-50 px-1.5 py-0.5 text-[11px] text-blue-700 dark:bg-blue-950 dark:text-blue-300"
                    >
                        {{ $t('list.actionsOk', { count: message.actions_ok }) }}
                    </span>
                </div>
            </button>
        </div>

        <div class="flex items-center gap-2 border-t border-slate-200 bg-white px-4 py-1.5 text-xs text-slate-500 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-400">
            <span>{{ $t('list.messageCount', { count: meta.total ?? 0 }) }}</span>

            <template v-if="meta.last_page > 1">
                <span class="ml-auto">{{ $t('list.page', { current: meta.current_page, last: meta.last_page }) }}</span>
                <button
                    class="btn-ghost disabled:opacity-40"
                    :disabled="meta.current_page <= 1"
                    @click="setFilter('page', meta.current_page - 1)"
                >
                    {{ $t('list.newer') }}
                </button>
                <button
                    class="btn-ghost disabled:opacity-40"
                    :disabled="meta.current_page >= meta.last_page"
                    @click="setFilter('page', meta.current_page + 1)"
                >
                    {{ $t('list.older') }}
                </button>
            </template>
        </div>
    </section>
</template>

