<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';
import { copyText } from '../format';
import ModalShell from './ModalShell.vue';
import { t } from '../i18n';

const props = defineProps({
    endpointId: { type: Number, required: true },
});

const emit = defineEmits(['close', 'changed', 'notify']);

const form = ref(null);
const saving = ref(false);

onMounted(async () => {
    try {
        form.value = await api.endpoint(props.endpointId);
    } catch (error) {
        emit('notify', error.message, 'error');
        emit('close');
    }
});

const save = async () => {
    saving.value = true;

    try {
        await api.updateEndpoint(props.endpointId, {
            name: form.value.name,
            description: form.value.description,
            enabled: form.value.enabled,
            response_status: Number(form.value.response_status),
            response_body: form.value.response_body,
            response_content_type: form.value.response_content_type,
            response_delay_ms: Number(form.value.response_delay_ms),
            cors: form.value.cors,
            retention_days: form.value.retention_days ? Number(form.value.retention_days) : null,
            max_messages: form.value.max_messages ? Number(form.value.max_messages) : null,
        });
        emit('notify', t('common.saved'), 'success');
        emit('changed');
        emit('close');
    } catch (error) {
        emit('notify', error.message, 'error');
    } finally {
        saving.value = false;
    }
};

const rotate = async () => {
    if (!window.confirm(t('endpoint.confirmRotate'))) return;

    try {
        form.value = await api.rotateSecret(props.endpointId);
        emit('notify', t('endpoint.rotated'), 'success');
        emit('changed');
    } catch (error) {
        emit('notify', error.message, 'error');
    }
};

const copyUrl = async () => {
    await copyText(form.value.url);
    emit('notify', t('tree.urlCopied'), 'success');
};
</script>

<template>
    <ModalShell
        v-if="form"
        :title="form.name"
        :subtitle="form.group_path ? $t('endpoint.inGroup', { path: form.group_path }) : $t('endpoint.ungrouped')"
        @close="emit('close')"
    >
        <div class="space-y-4">
            <div>
                <label class="lbl">{{ $t('endpoint.webhookUrl') }}</label>
                <div class="flex gap-2">
                    <code class="min-w-0 flex-1 truncate rounded-lg bg-slate-100 px-3 py-2 font-mono text-xs dark:bg-slate-800">{{ form.url }}</code>
                    <button class="btn-secondary" @click="copyUrl">{{ $t('common.copy') }}</button>
                    <button class="btn-secondary text-amber-700 dark:text-amber-300" @click="rotate">{{ $t('endpoint.newSecret') }}</button>
                </div>
                <p class="hint">{{ $t('endpoint.urlHint') }}</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="lbl">{{ $t('common.name') }}</label>
                    <input v-model="form.name" class="inp" />
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 pb-2 text-sm text-slate-700 dark:text-slate-300">
                        <input v-model="form.enabled" type="checkbox" class="rounded border-slate-300 dark:border-slate-700" />
                        {{ $t('endpoint.enabled') }}
                    </label>
                </div>
            </div>

            <div>
                <label class="lbl">{{ $t('common.description') }}</label>
                <textarea v-model="form.description" rows="2" class="inp"></textarea>
            </div>

            <fieldset class="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t('endpoint.responseLegend') }}</legend>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="lbl">{{ $t('endpoint.statusCode') }}</label>
                        <input v-model="form.response_status" type="number" class="inp" />
                    </div>
                    <div>
                        <label class="lbl">Content-Type</label>
                        <input v-model="form.response_content_type" class="inp" />
                    </div>
                    <div>
                        <label class="lbl">{{ $t('endpoint.delay') }}</label>
                        <input v-model="form.response_delay_ms" type="number" class="inp" />
                    </div>
                </div>

                <div class="mt-3">
                    <label class="lbl">{{ $t('endpoint.responseBody') }}</label>
                    <textarea v-model="form.response_body" rows="2" class="inp font-mono text-xs"></textarea>
                </div>

                <label class="mt-3 flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input v-model="form.cors" type="checkbox" class="rounded border-slate-300 dark:border-slate-700" />
                    {{ $t('endpoint.cors') }}
                </label>
            </fieldset>

            <fieldset class="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t('endpoint.retentionLegend') }}</legend>
                <p class="hint mb-2">{{ $t('endpoint.retentionHint') }}</p>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="lbl">{{ $t('endpoint.retentionDays') }}</label>
                        <input v-model="form.retention_days" type="number" :placeholder="$t('endpoint.never')" class="inp" />
                    </div>
                    <div>
                        <label class="lbl">{{ $t('endpoint.maxMessages') }}</label>
                        <input v-model="form.max_messages" type="number" :placeholder="$t('endpoint.unlimited')" class="inp" />
                    </div>
                </div>
            </fieldset>
        </div>

        <template #footer>
            <button class="btn-primary" :disabled="saving" @click="save">{{ $t('common.save') }}</button>
            <button class="btn-secondary" @click="emit('close')">{{ $t('common.cancel') }}</button>
            <span class="ml-auto text-xs text-slate-400 dark:text-slate-500">{{ $t('endpoint.storedMessages', { count: form.messages_count }) }}</span>
        </template>
    </ModalShell>
</template>
