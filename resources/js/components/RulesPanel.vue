<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';
import ModalShell from './ModalShell.vue';
import RuleEditor from './RuleEditor.vue';

const props = defineProps({
    selection: { type: Object, required: true },
    sampleMessage: { type: String, default: null },
});

const emit = defineEmits(['close', 'changed', 'notify']);

const rules = ref([]);
const loading = ref(true);
const editing = ref(null);

const load = async () => {
    loading.value = true;

    try {
        rules.value = await api.rules(
            props.selection.type === 'endpoint'
                ? { endpoint_id: props.selection.id }
                : { group_id: props.selection.id },
        );
    } catch (error) {
        emit('notify', error.message, 'error');
    } finally {
        loading.value = false;
    }
};

onMounted(load);

const blank = () => ({
    name: '',
    enabled: true,
    priority: 100,
    stop_processing: false,
    group_id: props.selection.type === 'group' ? props.selection.id : null,
    endpoint_id: props.selection.type === 'endpoint' ? props.selection.id : null,
    conditions: { type: 'group', op: 'and', children: [] },
    actions: [],
});

const remove = async (rule) => {
    if (!window.confirm(`Törlöd a(z) "${rule.name}" szabályt?`)) return;

    try {
        await api.deleteRule(rule.id);
        emit('notify', 'Szabály törölve', 'success');
        emit('changed');
        load();
    } catch (error) {
        emit('notify', error.message, 'error');
    }
};

const toggle = async (rule) => {
    try {
        await api.updateRule(rule.id, { ...rule, enabled: !rule.enabled });
        emit('changed');
        load();
    } catch (error) {
        emit('notify', error.message, 'error');
    }
};

const saved = () => {
    editing.value = null;
    emit('changed');
    load();
};
</script>

<template>
    <RuleEditor
        v-if="editing"
        :rule="editing"
        :sample-message="sampleMessage"
        :scope-label="selection.name"
        @close="editing = null"
        @saved="saved"
        @notify="(m, k) => emit('notify', m, k)"
    />

    <ModalShell
        v-else
        title="Szabályok"
        :subtitle="`${selection.type === 'group' ? 'Csoport' : 'URL'}: ${selection.name}`"
        wide
        @close="emit('close')"
    >
        <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">
            <template v-if="selection.type === 'group'">
                A csoportra tett szabály az alatta lévő <strong>összes</strong> URL-re lefut.
            </template>
            <template v-else>
                Itt az ehhez az URL-hez tartozó saját szabályok látszanak. A fölötte lévő csoportok szabályai
                szintén lefutnak, azokat a csoport során lehet szerkeszteni.
            </template>
        </p>

        <p v-if="loading" class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">Betöltés…</p>

        <p v-else-if="!rules.length" class="rounded-lg border border-dashed border-slate-300 py-8 text-center text-sm text-slate-400 dark:border-slate-700 dark:text-slate-500">
            Még nincs szabály.
        </p>

        <div
            v-for="rule in rules"
            :key="rule.id"
            class="mb-2 flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-800"
            :class="{ 'opacity-60': !rule.enabled }"
        >
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">{{ rule.name }}</p>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                    {{ rule.actions?.length ?? 0 }} akció · prioritás {{ rule.priority }}
                    <span v-if="rule.match_count"> · {{ rule.match_count }}× illeszkedett</span>
                    <span v-if="rule.stop_processing"> · itt megáll a feldolgozás</span>
                </p>
            </div>

            <button class="btn-secondary text-xs" @click="toggle(rule)">
                {{ rule.enabled ? 'Szünetel' : 'Bekapcsol' }}
            </button>
            <button class="btn-secondary text-xs" @click="editing = { ...rule }">Szerkesztés</button>
            <button class="btn-danger text-xs" @click="remove(rule)">Törlés</button>
        </div>

        <template #footer>
            <button class="btn-primary" @click="editing = blank()">+ Új szabály</button>
            <button class="btn-secondary" @click="emit('close')">Bezár</button>
        </template>
    </ModalShell>
</template>
