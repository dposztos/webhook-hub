<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';
import ModalShell from './ModalShell.vue';
import RuleEditor from './RuleEditor.vue';
import { t } from '../i18n';

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
    if (!window.confirm(t('rules.confirmDelete', { name: rule.name }))) return;

    try {
        await api.deleteRule(rule.id);
        emit('notify', t('rules.deleted'), 'success');
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
        :title="$t('rules.title')"
        :subtitle="$t(selection.type === 'group' ? 'rules.scopeGroup' : 'rules.scopeEndpoint', { name: selection.name })"
        wide
        @close="emit('close')"
    >
        <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">
            <template v-if="selection.type === 'group'">{{ $t('rules.groupHint') }}</template>
            <template v-else>{{ $t('rules.endpointHint') }}</template>
        </p>

        <p v-if="loading" class="py-6 text-center text-sm text-slate-400 dark:text-slate-500">{{ $t('common.loading') }}</p>

        <p v-else-if="!rules.length" class="rounded-lg border border-dashed border-slate-300 py-8 text-center text-sm text-slate-400 dark:border-slate-700 dark:text-slate-500">
            {{ $t('rules.empty') }}
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
                    {{ $t('rules.summary', { count: rule.actions?.length ?? 0, priority: rule.priority }) }}
                    <span v-if="rule.match_count"> · {{ $t('rules.matchCount', { count: rule.match_count }) }}</span>
                    <span v-if="rule.stop_processing"> · {{ $t('rules.stopsHere') }}</span>
                </p>
            </div>

            <button class="btn-secondary text-xs" @click="toggle(rule)">
                {{ rule.enabled ? $t('rules.pause') : $t('rules.enable') }}
            </button>
            <button class="btn-secondary text-xs" @click="editing = { ...rule }">{{ $t('common.edit') }}</button>
            <button class="btn-danger text-xs" @click="remove(rule)">{{ $t('common.delete') }}</button>
        </div>

        <template #footer>
            <button class="btn-primary" @click="editing = blank()">{{ $t('rules.new') }}</button>
            <button class="btn-secondary" @click="emit('close')">{{ $t('common.close') }}</button>
        </template>
    </ModalShell>
</template>
