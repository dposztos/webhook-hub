<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';
import { copyText } from '../format';
import ModalShell from './ModalShell.vue';

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
        emit('notify', 'Mentve', 'success');
        emit('changed');
        emit('close');
    } catch (error) {
        emit('notify', error.message, 'error');
    } finally {
        saving.value = false;
    }
};

const rotate = async () => {
    if (!window.confirm('Új titkot generálunk: a jelenlegi URL azonnal érvénytelen lesz. Folytatod?')) return;

    try {
        form.value = await api.rotateSecret(props.endpointId);
        emit('notify', 'Új URL létrehozva – frissítsd a küldő rendszerben!', 'success');
        emit('changed');
    } catch (error) {
        emit('notify', error.message, 'error');
    }
};

const copyUrl = async () => {
    await copyText(form.value.url);
    emit('notify', 'URL a vágólapon', 'success');
};
</script>

<template>
    <ModalShell
        v-if="form"
        :title="form.name"
        :subtitle="form.group_path ? `Csoport: ${form.group_path}` : 'Csoport nélküli URL'"
        @close="emit('close')"
    >
        <div class="space-y-4">
            <div>
                <label class="lbl">Webhook URL</label>
                <div class="flex gap-2">
                    <code class="min-w-0 flex-1 truncate rounded-lg bg-slate-100 px-3 py-2 font-mono text-xs">{{ form.url }}</code>
                    <button class="btn-secondary" @click="copyUrl">Másolás</button>
                    <button class="btn-secondary text-amber-700" @click="rotate">Új titok</button>
                </div>
                <p class="hint">Az útvonal a csoport-hierarchiából és a névből áll, a végén a titok. Átnevezéskor az URL nem változik.</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="lbl">Név</label>
                    <input v-model="form.name" class="inp" />
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 pb-2 text-sm text-slate-700">
                        <input v-model="form.enabled" type="checkbox" class="rounded border-slate-300" />
                        Aktív (kikapcsolva 404-et ad)
                    </label>
                </div>
            </div>

            <div>
                <label class="lbl">Leírás</label>
                <textarea v-model="form.description" rows="2" class="inp"></textarea>
            </div>

            <fieldset class="rounded-lg border border-slate-200 p-3">
                <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Válasz a küldőnek</legend>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="lbl">Státuszkód</label>
                        <input v-model="form.response_status" type="number" class="inp" />
                    </div>
                    <div>
                        <label class="lbl">Content-Type</label>
                        <input v-model="form.response_content_type" class="inp" />
                    </div>
                    <div>
                        <label class="lbl">Késleltetés (ms)</label>
                        <input v-model="form.response_delay_ms" type="number" class="inp" />
                    </div>
                </div>

                <div class="mt-3">
                    <label class="lbl">Válasz-test</label>
                    <textarea v-model="form.response_body" rows="2" class="inp font-mono text-xs"></textarea>
                </div>

                <label class="mt-3 flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.cors" type="checkbox" class="rounded border-slate-300" />
                    CORS fejlécek küldése
                </label>
            </fieldset>

            <fieldset class="rounded-lg border border-slate-200 p-3">
                <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Megőrzés</legend>
                <p class="hint mb-2">Üresen hagyva minden üzenet örökre megmarad.</p>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="lbl">Törlés ennyi nap után</label>
                        <input v-model="form.retention_days" type="number" placeholder="soha" class="inp" />
                    </div>
                    <div>
                        <label class="lbl">Legfeljebb ennyi üzenet</label>
                        <input v-model="form.max_messages" type="number" placeholder="korlátlan" class="inp" />
                    </div>
                </div>
            </fieldset>
        </div>

        <template #footer>
            <button class="btn-primary" :disabled="saving" @click="save">Mentés</button>
            <button class="btn-secondary" @click="emit('close')">Mégse</button>
            <span class="ml-auto text-xs text-slate-400">{{ form.messages_count }} tárolt üzenet</span>
        </template>
    </ModalShell>
</template>
