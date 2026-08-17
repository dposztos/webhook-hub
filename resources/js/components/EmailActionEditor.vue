<script setup>
import { ref } from 'vue';
import { api } from '../api';

const props = defineProps({
    action: { type: Object, required: true },
    sampleMessage: { type: String, default: null },
});

const emit = defineEmits(['update', 'remove', 'notify']);

const preview = ref(null);
const testing = ref(false);
const testAddress = ref('');

const patch = (changes) => emit('update', { ...props.action, config: { ...props.action.config, ...changes } });

const DEFAULT_TEMPLATE = `<style>
  .wrap { font-family: -apple-system, Segoe UI, Roboto, sans-serif; color: #0f172a; }
  .title { font-size: 18px; font-weight: 700; }
  .muted { color: #64748b; font-size: 12px; }
</style>
<div class="wrap">
  <p class="title">Új webhook érkezett: {{ endpoint.name }}</p>
  <p>Esemény: <strong>{{ json.event|default('—') }}</strong></p>
  {{ json|table }}
  <p class="muted">Beérkezett: {{ meta.received_at_hu }}</p>
</div>`;

const insertTemplate = () => patch({ body_html: DEFAULT_TEMPLATE });

// Sablon-példák a súgóhoz. Azért változóban, mert a "}}" a Vue sablonban
// idézőjelek között is lezárná az interpolációt.
const examples = {
    variable: '{{ json.mezo.almezo }}',
    header: '{{ headers["x-signature"] }}',
    received: '{{ meta.received_at_hu }}',
    loop: '{% for tetel in json.items %}…{% endfor %}',
    money: '{{ json.osszeg|huf }}',
};

const runPreview = async (send = false) => {
    if (!props.sampleMessage) {
        emit('notify', 'A próbához kell egy már beérkezett üzenet ezen az URL-en.', 'error');
        return;
    }

    if (send && !testAddress.value) {
        emit('notify', 'Add meg, melyik címre menjen a teszt-levél.', 'error');
        return;
    }

    testing.value = true;

    try {
        preview.value = await api.testAction({
            type: 'email',
            config: props.action.config,
            message_uuid: props.sampleMessage,
            send_to: send ? testAddress.value : null,
        });

        if (send && preview.value.status === 'success') {
            emit('notify', 'Teszt-levél elküldve', 'success');
        }
    } catch (error) {
        emit('notify', error.message, 'error');
    } finally {
        testing.value = false;
    }
};
</script>

<template>
    <div class="rounded-lg border border-slate-200 dark:border-slate-800">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2 dark:bg-slate-800/40 dark:border-slate-800">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">E-mail küldése</span>
            <label class="ml-2 flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                <input
                    type="checkbox"
                    :checked="action.enabled !== false"
                    class="rounded border-slate-300 dark:border-slate-700"
                    @change="emit('update', { ...action, enabled: $event.target.checked })"
                />
                aktív
            </label>
            <button class="ml-auto btn-danger text-xs" @click="emit('remove')">Akció törlése</button>
        </div>

        <div class="space-y-3 p-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="lbl">Címzett(ek)</label>
                    <input
                        :value="action.config.to"
                        class="inp font-mono text-xs"
                        placeholder="{{ json.customer.email }}, iroda@ceg.hu"
                        @input="patch({ to: $event.target.value })"
                    />
                </div>
                <div>
                    <label class="lbl">Másolat (cc)</label>
                    <input
                        :value="action.config.cc"
                        class="inp font-mono text-xs"
                        placeholder="opcionális"
                        @input="patch({ cc: $event.target.value })"
                    />
                </div>
            </div>

            <div>
                <label class="lbl">Tárgy</label>
                <input
                    :value="action.config.subject"
                    class="inp font-mono text-xs"
                    placeholder="Új rendelés: {{ json.order.id }}"
                    @input="patch({ subject: $event.target.value })"
                />
            </div>

            <div>
                <div class="flex items-center gap-2">
                    <label class="lbl mb-0">Levél törzse (HTML + változók)</label>
                    <button class="ml-auto text-xs text-blue-600 hover:underline dark:text-blue-400" @click="insertTemplate">
                        Alapsablon beillesztése
                    </button>
                </div>
                <textarea
                    :value="action.config.body_html"
                    rows="12"
                    class="inp mt-1 font-mono text-xs"
                    placeholder="<h1>Szia {{ json.name }}</h1>"
                    @input="patch({ body_html: $event.target.value })"
                ></textarea>
                <p class="hint">
                    Változók: <code>{{ examples.variable }}</code>, <code>{{ examples.header }}</code>,
                    <code>{{ examples.received }}</code>. Szűrők: <code>{{ examples.money }}</code>,
                    <code>|table</code>, <code>|json_pretty</code>, <code>|hu_date('Y.m.d')</code>,
                    <code>|default('—')</code>. Ciklus és feltétel is használható:
                    <code>{{ examples.loop }}</code>.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <label class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400">
                    <input
                        type="checkbox"
                        :checked="action.config.inline_css !== false"
                        class="rounded border-slate-300 dark:border-slate-700"
                        @change="patch({ inline_css: $event.target.checked })"
                    />
                    CSS beágyazása a stílusokba (levelezőkliens-barát)
                </label>
                <label class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400">
                    <input
                        type="checkbox"
                        :checked="!!action.config.attach_json"
                        class="rounded border-slate-300 dark:border-slate-700"
                        @change="patch({ attach_json: $event.target.checked })"
                    />
                    JSON csatolása mellékletként
                </label>
            </div>

            <div class="flex flex-wrap items-center gap-2 rounded-lg bg-slate-50 p-2 dark:bg-slate-800/40">
                <button class="btn-secondary text-xs" :disabled="testing" @click="runPreview(false)">
                    Előnézet a legutóbbi üzenettel
                </button>
                <input v-model="testAddress" placeholder="teszt cím…" class="inp w-48 text-xs" />
                <button class="btn-secondary text-xs" :disabled="testing" @click="runPreview(true)">
                    Teszt-levél küldése
                </button>
            </div>

            <div v-if="preview" class="rounded-lg border border-slate-200 dark:border-slate-800">
                <div class="border-b border-slate-200 bg-white px-3 py-2 text-xs dark:bg-slate-900 dark:border-slate-800">
                    <p v-if="preview.error" class="text-red-600 dark:text-red-400">{{ preview.error }}</p>
                    <template v-else>
                        <p><strong>Címzett:</strong> {{ preview.preview.to.join(', ') || '—' }}</p>
                        <p><strong>Tárgy:</strong> {{ preview.preview.subject }}</p>
                    </template>
                </div>
                <iframe
                    v-if="preview.preview?.html"
                    :srcdoc="preview.preview.html"
                    sandbox=""
                    class="h-64 w-full bg-white dark:bg-slate-900"
                ></iframe>
            </div>
        </div>
    </div>
</template>
