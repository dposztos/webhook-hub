<script setup>
import { ref } from 'vue';
import { api } from '../api';
import ModalShell from './ModalShell.vue';

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
        emit('notify', 'Mentve', 'success');
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
    <ModalShell title="Csoport beállításai" :subtitle="group.name" @close="emit('close')">
        <div class="space-y-4">
            <div>
                <label class="lbl">Név</label>
                <input v-model="name" class="inp" />
                <p class="hint">A név átírása nem változtatja meg a már kiadott webhook URL-eket.</p>
            </div>
            <div>
                <label class="lbl">Leírás</label>
                <textarea v-model="description" rows="3" class="inp"></textarea>
            </div>
        </div>

        <template #footer>
            <button class="btn-primary" :disabled="saving" @click="save">Mentés</button>
            <button class="btn-secondary" @click="emit('close')">Mégse</button>
        </template>
    </ModalShell>
</template>
