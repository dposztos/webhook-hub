<script setup>
import { ref } from 'vue';
import { api } from '../api';
import ModalShell from './ModalShell.vue';
import ConditionNode from './ConditionNode.vue';
import EmailActionEditor from './EmailActionEditor.vue';
import { t } from '../i18n';

const props = defineProps({
    rule: { type: Object, required: true },
    sampleMessage: { type: String, default: null },
    scopeLabel: { type: String, default: '' },
});

const emit = defineEmits(['close', 'saved', 'notify']);

const form = ref({
    ...props.rule,
    conditions: props.rule.conditions ?? { type: 'group', op: 'and', children: [] },
    actions: (props.rule.actions ?? []).map((action) => ({ ...action, config: { ...action.config } })),
});

const saving = ref(false);
const testResult = ref(null);

const addEmailAction = () => {
    form.value.actions.push({
        type: 'email',
        name: t('rules.emailAction'),
        enabled: true,
        config: { to: '', cc: '', subject: '', body_html: '', inline_css: true },
    });
};

const testConditions = async () => {
    if (!props.sampleMessage) {
        emit('notify', t('rules.testNeedsMessage'), 'error');
        return;
    }

    try {
        testResult.value = await api.testConditions({
            conditions: form.value.conditions,
            message_uuid: props.sampleMessage,
        });
    } catch (error) {
        emit('notify', error.message, 'error');
    }
};

const save = async () => {
    if (!form.value.name?.trim()) {
        emit('notify', t('rules.nameRequired'), 'error');
        return;
    }

    saving.value = true;

    const payload = {
        name: form.value.name,
        description: form.value.description ?? null,
        enabled: form.value.enabled ?? true,
        priority: Number(form.value.priority ?? 100),
        stop_processing: !!form.value.stop_processing,
        group_id: form.value.group_id ?? null,
        endpoint_id: form.value.endpoint_id ?? null,
        conditions: form.value.conditions,
        actions: form.value.actions,
    };

    try {
        form.value.id ? await api.updateRule(form.value.id, payload) : await api.createRule(payload);
        emit('notify', t('rules.saved'), 'success');
        emit('saved');
    } catch (error) {
        emit('notify', error.message, 'error');
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <ModalShell
        :title="form.id ? $t('rules.editTitle') : $t('rules.newTitle')"
        :subtitle="scopeLabel"
        wide
        @close="emit('close')"
    >
        <div class="space-y-5">
            <div class="grid grid-cols-4 gap-3">
                <div class="col-span-2">
                    <label class="lbl">{{ $t('common.name') }}</label>
                    <input v-model="form.name" class="inp" :placeholder="$t('rules.namePlaceholder')" />
                </div>
                <div>
                    <label class="lbl">{{ $t('rules.priority') }}</label>
                    <input v-model="form.priority" type="number" class="inp" />
                </div>
                <div class="flex items-end gap-3 pb-1.5">
                    <label class="flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                        <input v-model="form.enabled" type="checkbox" class="rounded border-slate-300 dark:border-slate-700" />
                        {{ $t('rules.active') }}
                    </label>
                    <label class="flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300" :title="$t('rules.stopHereTitle')">
                        <input v-model="form.stop_processing" type="checkbox" class="rounded border-slate-300 dark:border-slate-700" />
                        {{ $t('rules.stopHere') }}
                    </label>
                </div>
            </div>

            <section>
                <div class="mb-2 flex items-center gap-2">
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $t('rules.whenHeading') }}</h3>
                    <button class="ml-auto btn-secondary text-xs" @click="testConditions">
                        {{ $t('rules.testButton') }}
                    </button>
                </div>

                <ConditionNode
                    :node="form.conditions"
                    root
                    @update="form.conditions = $event"
                />

                <div
                    v-if="testResult"
                    class="mt-2 rounded-lg p-3 text-xs ring-1"
                    :class="testResult.matched ? 'bg-emerald-50 text-emerald-800 ring-emerald-100 dark:bg-emerald-950/50 dark:text-emerald-200 dark:ring-emerald-900' : 'bg-slate-50 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700'"
                >
                    <p class="font-medium">
                        {{ testResult.matched ? $t('rules.testMatched') : $t('rules.testNotMatched') }}
                    </p>
                    <ul class="mt-1 space-y-0.5 font-mono">
                        <li v-for="(line, index) in testResult.trace" :key="index">{{ line }}</li>
                    </ul>
                </div>
            </section>

            <section>
                <div class="mb-2 flex items-center gap-2">
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $t('rules.thenHeading') }}</h3>
                    <button class="ml-auto btn-secondary text-xs" @click="addEmailAction">{{ $t('rules.addEmailAction') }}</button>
                </div>

                <p v-if="!form.actions.length" class="rounded-lg border border-dashed border-slate-300 py-6 text-center text-sm text-slate-400 dark:border-slate-700 dark:text-slate-500">
                    {{ $t('rules.noActions') }}
                </p>

                <div class="space-y-3">
                    <EmailActionEditor
                        v-for="(action, index) in form.actions"
                        :key="index"
                        :action="action"
                        :sample-message="sampleMessage"
                        @update="form.actions[index] = $event"
                        @remove="form.actions.splice(index, 1)"
                        @notify="(m, k) => emit('notify', m, k)"
                    />
                </div>
            </section>
        </div>

        <template #footer>
            <button class="btn-primary" :disabled="saving" @click="save">{{ $t('common.save') }}</button>
            <button class="btn-secondary" @click="emit('close')">{{ $t('common.cancel') }}</button>
        </template>
    </ModalShell>
</template>
