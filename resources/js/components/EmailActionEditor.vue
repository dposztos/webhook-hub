<script setup>
import { ref } from 'vue';
import { api } from '../api';
import { t } from '../i18n';

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
  <p class="title">New webhook on {{ endpoint.name }}</p>
  <p>Event: <strong>{{ json.event|default('—') }}</strong></p>
  {{ json|table }}
  <p class="muted">Received: {{ meta.received_at_local }}</p>
</div>`;

const insertTemplate = () => patch({ body_html: DEFAULT_TEMPLATE });

// Template examples for the hint text. Kept in a variable because "}}" would
// close the interpolation inside a Vue template even within quotes.
const examples = {
    variable: '{{ json.field.subfield }}',
    header: '{{ headers["x-signature"] }}',
    received: '{{ meta.received_at_local }}',
    loop: '{% for item in json.items %}…{% endfor %}',
    money: '{{ json.total|money }}',
};

const runPreview = async (send = false) => {
    if (!props.sampleMessage) {
        emit('notify', t('email.needsMessage'), 'error');
        return;
    }

    if (send && !testAddress.value) {
        emit('notify', t('email.needsTestAddress'), 'error');
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
            emit('notify', t('email.testSent'), 'success');
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
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('email.title') }}</span>
            <label class="ml-2 flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                <input
                    type="checkbox"
                    :checked="action.enabled !== false"
                    class="rounded border-slate-300 dark:border-slate-700"
                    @change="emit('update', { ...action, enabled: $event.target.checked })"
                />
                {{ $t('rules.active') }}
            </label>
            <button class="ml-auto btn-danger text-xs" @click="emit('remove')">{{ $t('email.removeAction') }}</button>
        </div>

        <div class="space-y-3 p-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="lbl">{{ $t('email.to') }}</label>
                    <input
                        :value="action.config.to"
                        class="inp font-mono text-xs"
                        placeholder="{{ json.customer.email }}, iroda@ceg.hu"
                        @input="patch({ to: $event.target.value })"
                    />
                </div>
                <div>
                    <label class="lbl">{{ $t('email.cc') }}</label>
                    <input
                        :value="action.config.cc"
                        class="inp font-mono text-xs"
                        :placeholder="$t('common.optional')"
                        @input="patch({ cc: $event.target.value })"
                    />
                </div>
            </div>

            <div>
                <label class="lbl">{{ $t('email.subject') }}</label>
                <input
                    :value="action.config.subject"
                    class="inp font-mono text-xs"
                    :placeholder="$t('email.subjectPlaceholder')"
                    @input="patch({ subject: $event.target.value })"
                />
            </div>

            <div>
                <div class="flex items-center gap-2">
                    <label class="lbl mb-0">{{ $t('email.body') }}</label>
                    <button class="ml-auto text-xs text-blue-600 hover:underline dark:text-blue-400" @click="insertTemplate">
                        {{ $t('email.insertTemplate') }}
                    </button>
                </div>
                <textarea
                    :value="action.config.body_html"
                    rows="12"
                    class="inp mt-1 font-mono text-xs"
                    :placeholder="$t('email.bodyPlaceholder')"
                    @input="patch({ body_html: $event.target.value })"
                ></textarea>
                <p class="hint">
                    {{ $t('email.hintVariables') }} <code>{{ examples.variable }}</code>,
                    <code>{{ examples.header }}</code>, <code>{{ examples.received }}</code>.
                    {{ $t('email.hintFilters') }} <code>{{ examples.money }}</code>, <code>|table</code>,
                    <code>|json_pretty</code>, <code>|date('Y-m-d')</code>, <code>|default('—')</code>.
                    {{ $t('email.hintLoops') }} <code>{{ examples.loop }}</code>.
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
                    {{ $t('email.inlineCss') }}
                </label>
                <label class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400">
                    <input
                        type="checkbox"
                        :checked="!!action.config.attach_json"
                        class="rounded border-slate-300 dark:border-slate-700"
                        @change="patch({ attach_json: $event.target.checked })"
                    />
                    {{ $t('email.attachJson') }}
                </label>
            </div>

            <div class="flex flex-wrap items-center gap-2 rounded-lg bg-slate-50 p-2 dark:bg-slate-800/40">
                <button class="btn-secondary text-xs" :disabled="testing" @click="runPreview(false)">
                    {{ $t('email.preview') }}
                </button>
                <input v-model="testAddress" :placeholder="$t('email.testAddress')" class="inp w-48 text-xs" />
                <button class="btn-secondary text-xs" :disabled="testing" @click="runPreview(true)">
                    {{ $t('email.sendTest') }}
                </button>
            </div>

            <div v-if="preview" class="rounded-lg border border-slate-200 dark:border-slate-800">
                <div class="border-b border-slate-200 bg-white px-3 py-2 text-xs dark:bg-slate-900 dark:border-slate-800">
                    <p v-if="preview.error" class="text-red-600 dark:text-red-400">{{ preview.error }}</p>
                    <template v-else>
                        <p><strong>{{ $t('email.to') }}:</strong> {{ preview.preview.to.join(', ') || '—' }}</p>
                        <p><strong>{{ $t('email.subject') }}:</strong> {{ preview.preview.subject }}</p>
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
