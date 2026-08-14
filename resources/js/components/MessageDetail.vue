<script setup>
import { computed, ref, watch } from 'vue';
import { api } from '../api';
import { copyText, formatSize, formatTime, methodColor } from '../format';
import JsonView from './JsonView.vue';

const props = defineProps({
    detail: { type: Object, default: null },
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
    emit('notify', `${label} a vágólapon`, 'success');
};

const copyPath = async (path) => {
    await copyText(`{{ ${path} }}`);
    emit('notify', `Sablon-hivatkozás másolva: {{ ${path} }}`, 'success');
};

const replay = async () => {
    try {
        await api.replayMessage(props.detail.uuid);
        emit('notify', 'Szabályok újrafuttatva – pár másodperc múlva frissül', 'success');
        setTimeout(() => emit('changed'), 1500);
    } catch (error) {
        emit('notify', error.message, 'error');
    }
};

const remove = async () => {
    if (!window.confirm('Törlöd ezt az üzenetet?')) return;

    try {
        await api.deleteMessage(props.detail.uuid);
        emit('notify', 'Üzenet törölve', 'success');
        emit('close');
        emit('changed');
    } catch (error) {
        emit('notify', error.message, 'error');
    }
};

const statusClass = (status) =>
    ({
        success: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
        failed: 'bg-red-50 text-red-700 ring-red-100',
        skipped: 'bg-slate-50 text-slate-600 ring-slate-200',
    })[status] ?? 'bg-slate-50 text-slate-600 ring-slate-200';
</script>

<template>
    <section v-if="detail" class="flex w-[38rem] shrink-0 flex-col bg-white">
        <div class="flex items-start gap-2 border-b border-slate-200 px-4 py-2.5">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="rounded px-1.5 py-0.5 text-xs font-semibold" :class="methodColor(detail.method)">
                        {{ detail.method }}
                    </span>
                    <span class="text-sm text-slate-500">{{ formatTime(detail.created_at) }}</span>
                    <span class="text-xs text-slate-400">{{ formatSize(detail.size) }}</span>
                    <span v-if="detail.truncated" class="text-xs text-amber-600" title="A test csonkolva lett tárolva">
                        csonkolt
                    </span>
                </div>
                <p class="mt-0.5 truncate text-xs text-slate-400">
                    {{ detail.ip }} · {{ detail.content_type || 'nincs content-type' }}
                </p>
            </div>

            <div class="ml-auto flex shrink-0 items-center gap-1 text-xs">
                <button class="btn-ghost" title="Szabályok újrafuttatása erre az üzenetre" @click="replay">Újrafuttat</button>
                <button class="btn-ghost text-red-600" @click="remove">Törlés</button>
                <button class="btn-ghost" @click="emit('close')">✕</button>
            </div>
        </div>

        <nav class="flex gap-1 border-b border-slate-200 px-3 pt-2 text-sm">
            <button v-for="t in [
                    { key: 'json', label: 'JSON', show: !!detail.body_json },
                    { key: 'raw', label: 'Nyers test', show: true },
                    { key: 'headers', label: `Fejlécek (${headerRows.length})`, show: true },
                    { key: 'runs', label: `Akciók (${detail.runs?.length ?? 0})`, show: true },
                ].filter((t) => t.show)"
                :key="t.key"
                class="rounded-t-lg px-3 py-1.5"
                :class="tab === t.key ? 'bg-slate-100 font-medium text-slate-900' : 'text-slate-500 hover:text-slate-800'"
                @click="tab = t.key"
            >
                {{ t.label }}
            </button>
        </nav>

        <div class="min-h-0 flex-1 overflow-y-auto p-4">
            <template v-if="tab === 'json'">
                <p class="mb-2 text-xs text-slate-400">
                    Kattints egy mezőnévre: a sablonba illeszthető hivatkozás a vágólapra kerül.
                </p>
                <JsonView :value="detail.body_json" path="json" @pick="copyPath" />
            </template>

            <template v-else-if="tab === 'raw'">
                <div class="mb-2 flex justify-end">
                    <button class="btn-ghost text-xs" @click="copy(detail.body, 'Nyers test')">Másolás</button>
                </div>
                <pre class="overflow-x-auto whitespace-pre-wrap break-all rounded bg-slate-50 p-3 font-mono text-xs text-slate-700">{{ detail.body || '(üres)' }}</pre>
            </template>

            <template v-else-if="tab === 'headers'">
                <table class="w-full text-left text-xs">
                    <tbody>
                        <tr v-for="[name, value] in headerRows" :key="name" class="border-b border-slate-100 align-top">
                            <th class="w-48 py-1 pr-3 font-medium text-slate-500">{{ name }}</th>
                            <td class="break-all py-1 font-mono text-slate-700">{{ value }}</td>
                        </tr>
                    </tbody>
                </table>

                <template v-if="queryRows.length">
                    <h3 class="mb-1 mt-4 text-xs font-semibold uppercase tracking-wide text-slate-400">Query paraméterek</h3>
                    <table class="w-full text-left text-xs">
                        <tbody>
                            <tr v-for="[name, value] in queryRows" :key="name" class="border-b border-slate-100">
                                <th class="w-48 py-1 pr-3 font-medium text-slate-500">{{ name }}</th>
                                <td class="break-all py-1 font-mono text-slate-700">{{ value }}</td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </template>

            <template v-else>
                <p v-if="!detail.runs?.length" class="py-6 text-center text-sm text-slate-400">
                    Erre az üzenetre nem futott akció.<br />
                    <button class="mt-2 text-blue-600 hover:underline" @click="emit('make-rule')">
                        Szabály készítése ehhez az URL-hez
                    </button>
                </p>

                <div v-for="run in detail.runs" :key="run.id" class="mb-2 rounded-lg p-3 ring-1" :class="statusClass(run.status)">
                    <div class="flex items-center gap-2 text-xs">
                        <strong>{{ run.rule ?? 'törölt szabály' }}</strong>
                        <span class="opacity-70">· {{ run.type }} · {{ run.duration_ms }} ms</span>
                        <span class="ml-auto">{{ formatTime(run.created_at) }}</span>
                    </div>
                    <p class="mt-1 text-sm">{{ run.error ?? run.summary }}</p>
                    <p v-if="run.detail?.subject" class="mt-1 text-xs opacity-80">
                        Tárgy: {{ run.detail.subject }}
                    </p>
                </div>
            </template>
        </div>
    </section>
</template>

