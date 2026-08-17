<script setup>
import { ref } from 'vue';
import { api } from '../api';
import ModalShell from './ModalShell.vue';
import { t } from '../i18n';

const props = defineProps({
    group: { type: Object, required: true },
});

const emit = defineEmits(['close', 'changed', 'notify']);

const name = ref(props.group.name);
const description = ref(props.group.description ?? '');
const saving = ref(false);

const save = async () => {
    saving.value = true;

    try {
        await api.updateGroup(props.group.id, { name: name.value, description: description.value });
        emit('notify', t('common.saved'), 'success');
        emit('changed');
        emit('close');
    } catch (error) {
        emit('notify', error.message, 'error');
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <ModalShell :title="$t('group.title')" :subtitle="group.name" @close="emit('close')">
        <div class="space-y-4">
            <div>
                <label class="lbl">{{ $t('common.name') }}</label>
                <input v-model="name" class="inp" />
                <p class="hint">{{ $t('group.nameHint') }}</p>
            </div>
            <div>
                <label class="lbl">{{ $t('common.description') }}</label>
                <textarea v-model="description" rows="3" class="inp"></textarea>
            </div>
        </div>

        <template #footer>
            <button class="btn-primary" :disabled="saving" @click="save">{{ $t('common.save') }}</button>
            <button class="btn-secondary" @click="emit('close')">{{ $t('common.cancel') }}</button>
        </template>
    </ModalShell>
</template>
