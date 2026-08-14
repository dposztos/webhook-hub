<script setup>
import { computed } from 'vue';
import { api } from '../api';
import { copyText, formatSize, formatTime, methodColor } from '../format';

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
    emit('notify', 'URL a vágólapon', 'success');
};

const clearMessages = async () => {
    if (!window.confirm(`Törlöd a(z) "${props.selection.name}" összes üzenetét?`)) return;

    try {
        const result = await api.clearMessages(props.selection.id);
        emit('notify', `${result.deleted} üzenet törölve`, 'success');
        emit('changed');
    } catch (error) {
        emit('notify', error.message, 'error');
    }
};
</script>

<template>
    <section class="flex min-w-0 flex-1 flex-col border-r border-slate-200 bg-slate-50">
        <div v-if="selection" class="border-b border-slate-200 bg-white px-4 py-2.5">
            <div class="flex items-center gap-3">
                <h1 class="truncate text-sm font-semibold text-slate-900">
                    {{ selection.name }}
                    <span v-if="!isEndpoint" class="ml-1 font-normal text-slate-400">(csoport – minden alatta lévő URL)</span>
                </h1>

                <div class="ml-auto flex shrink-0 items-center gap-2 text-xs">
                    <button class="btn-ghost" @click="emit('rules')">Szabályok</button>
                    <button v-if="isEndpoint" class="btn-ghost" @click="emit('settings')">Beállítások</button>
                    <button v-if="isEndpoint" class="btn-ghost text-red-600" @click="clearMessages">Ürítés</button>
                </div>
            </div>

            <div v-if="isEndpoint && selection.url" class="mt-1.5 flex items-center gap-2">
                <code class="min-w-0 flex-1 truncate rounded bg-slate-100 px-2 py-1 font-mono text-xs text-slate-600">
                    {{ selection.url }}
                </code>
                <button class="btn-ghost shrink-0" @click="copyUrl">Másolás</button>
            </div>
        </div>

        <div class="flex items-center gap-2 border-b border-slate-200 bg-white px-4 py-2">
            <input
                :value="filters.q"
                placeholder="Keresés a testben, URL-ben, fejlécekben…"
                class="min-w-0 flex-1 rounded-lg border border-slate-300 px-2.5 py-1 text-sm outline-none focus:border-blue-500"
                @input="setFilter('q', $event.target.value)"
            />
            <select
                :value="filters.method"
                class="rounded-lg border border-slate-300 px-2 py-1 text-sm"
                @change="setFilter('method', $event.target.value)"
            >
                <option value="">Minden metódus</option>
                <option v-for="m in ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']" :key="m" :value="m">{{ m }}</option>
            </select>
            <select
                :value="filters.only"
                class="rounded-lg border border-slate-300 px-2 py-1 text-sm"
                @change="setFilter('only', $event.target.value)"
            >
                <option value="">Mind</option>
                <option value="matched">Csak illeszkedő</option>
                <option value="failed">Hibás akció</option>
                <option value="unprocessed">Feldolgozásra vár</option>
            </select>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
            <p v-if="loading && !messages.length" class="p-6 text-center text-sm text-slate-400">Betöltés…</p>

            <p v-else-if="!messages.length" class="p-8 text-center text-sm text-slate-400">
                Nincs üzenet.<br />
                <span v-if="isEndpoint">Küldj egy kérést a fenti URL-re, és itt azonnal megjelenik.</span>
            </p>

            <button
                v-for="message in messages"
                :key="message.uuid"
                class="block w-full border-b border-slate-200 px-4 py-2.5 text-left hover:bg-white"
                :class="{ 'bg-white ring-1 ring-inset ring-blue-200': message.uuid === activeUuid }"
                @click="emit('open', message.uuid)"
            >
                <div class="flex items-center gap-2 text-xs">
                    <span class="rounded px-1.5 py-0.5 font-semibold" :class="methodColor(message.method)">
                        {{ message.method }}
                    </span>
                    <span class="text-slate-500">{{ formatTime(message.created_at) }}</span>
                    <span v-if="!isEndpoint" class="truncate text-slate-400">· {{ message.endpoint.name }}</span>
                    <span class="ml-auto shrink-0 text-slate-400">{{ formatSize(message.size) }}</span>
                </div>

                <p class="mt-1 truncate font-mono text-xs text-slate-600">{{ message.preview || '—' }}</p>

                <div v-if="message.matched_rules?.length || message.actions_failed" class="mt-1 flex flex-wrap gap-1">
                    <span
                        v-for="rule in message.matched_rules"
                        :key="rule.id"
                        class="rounded bg-emerald-50 px-1.5 py-0.5 text-[11px] text-emerald-700"
                    >
                        ⚡ {{ rule.name }}
                    </span>
                    <span
                        v-if="message.actions_failed"
                        class="rounded bg-red-50 px-1.5 py-0.5 text-[11px] text-red-700"
                    >
                        {{ message.actions_failed }} hibás akció
                    </span>
                    <span
                        v-else-if="message.actions_ok"
                        class="rounded bg-blue-50 px-1.5 py-0.5 text-[11px] text-blue-700"
                    >
                        {{ message.actions_ok }} akció lefutott
                    </span>
                </div>
            </button>
        </div>

        <div class="flex items-center gap-2 border-t border-slate-200 bg-white px-4 py-1.5 text-xs text-slate-500">
            <span>{{ meta.total ?? 0 }} üzenet</span>

            <template v-if="meta.last_page > 1">
                <span class="ml-auto">{{ meta.current_page }}/{{ meta.last_page }}. oldal</span>
                <button
                    class="btn-ghost disabled:opacity-40"
                    :disabled="meta.current_page <= 1"
                    @click="setFilter('page', meta.current_page - 1)"
                >
                    ‹ Újabbak
                </button>
                <button
                    class="btn-ghost disabled:opacity-40"
                    :disabled="meta.current_page >= meta.last_page"
                    @click="setFilter('page', meta.current_page + 1)"
                >
                    Régebbiek ›
                </button>
            </template>
        </div>
    </section>
</template>

