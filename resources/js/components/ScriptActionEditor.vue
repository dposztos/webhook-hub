<script setup>
import { computed, onMounted, ref } from 'vue';
import { api } from '../api';
import { t } from '../i18n';

const props = defineProps({
    action: { type: Object, required: true },
    sampleMessage: { type: String, default: null },
    index: { type: Number, default: 0 },
});

// The same key the engine derives server-side, so the hint matches reality:
// the action's name slugified, or "step_<n>" when it has none.
const stepKey = computed(() => {
    const slug = (props.action.name ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');

    return slug || `step_${props.index + 1}`;
});

const emit = defineEmits(['update', 'remove', 'notify']);

const info = ref(null);
const result = ref(null);
const testing = ref(false);

const patch = (changes) => emit('update', { ...props.action, config: { ...props.action.config, ...changes } });

const inline = computed(() => props.action.config.source === 'inline');
const stdinMode = computed(() => props.action.config.stdin ?? 'json');

const EXAMPLE = `import json, sys

payload = json.load(sys.stdin)          # the whole captured message
event = payload["json"].get("event")

print(json.dumps({"handled": event}))   # JSON on stdout is parsed into the run log
sys.exit(0)                             # any other exit code marks the action failed`;

const insertExample = () => patch({ code: EXAMPLE });

onMounted(async () => {
    try {
        info.value = await api.scripts();
    } catch (error) {
        emit('notify', error.message, 'error');
    }
});

const run = async (live) => {
    if (!props.sampleMessage) {
        emit('notify', t('script.needsMessage'), 'error');
        return;
    }

    testing.value = true;

    try {
        result.value = await api.testAction({
            type: 'script',
            config: props.action.config,
            message_uuid: props.sampleMessage,
            run: live,
        });
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
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('script.title') }}</span>
            <label class="ml-2 flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                <input
                    type="checkbox"
                    :checked="action.enabled !== false"
                    class="rounded border-slate-300 dark:border-slate-700"
                    @change="emit('update', { ...action, enabled: $event.target.checked })"
                />
                {{ $t('rules.active') }}
            </label>
            <label
                class="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400"
                :title="$t('actions.onlyIfPreviousTitle')"
            >
                <input
                    type="checkbox"
                    :checked="!!action.config.only_if_previous_succeeded"
                    class="rounded border-slate-300 dark:border-slate-700"
                    @change="patch({ only_if_previous_succeeded: $event.target.checked })"
                />
                {{ $t('actions.onlyIfPrevious') }}
            </label>
            <span class="text-xs text-slate-400 dark:text-slate-600">{{ $t('actions.stepName', { key: stepKey }) }}</span>
            <button class="ml-auto btn-danger text-xs" @click="emit('remove')">{{ $t('email.removeAction') }}</button>
        </div>

        <div class="space-y-3 p-3">
            <p
                v-if="info && !info.enabled"
                class="rounded-lg bg-amber-50 p-2 text-xs text-amber-800 ring-1 ring-amber-100 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-900"
            >
                {{ $t('script.featureOff') }}
            </p>

            <div
                v-if="info?.requirements_error"
                class="rounded-lg bg-red-50 p-2 text-xs text-red-800 ring-1 ring-red-100 dark:bg-red-950/40 dark:text-red-200 dark:ring-red-900"
            >
                <p class="font-medium">{{ $t('script.requirementsFailed') }}</p>
                <pre class="mt-1 max-h-32 overflow-auto whitespace-pre-wrap font-mono text-[11px]">{{ info.requirements_error }}</pre>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400">
                    <input
                        type="radio"
                        :checked="!inline"
                        class="border-slate-300 dark:border-slate-700"
                        @change="patch({ source: 'file' })"
                    />
                    {{ $t('script.sourceFile') }}
                </label>
                <label
                    class="flex items-center gap-1.5 text-xs"
                    :class="info && !info.allow_inline ? 'text-slate-400 dark:text-slate-600' : 'text-slate-600 dark:text-slate-400'"
                    :title="info && !info.allow_inline ? $t('script.inlineOff') : ''"
                >
                    <input
                        type="radio"
                        :checked="inline"
                        :disabled="info ? !info.allow_inline : false"
                        class="border-slate-300 dark:border-slate-700"
                        @change="patch({ source: 'inline' })"
                    />
                    {{ $t('script.sourceInline') }}
                </label>
            </div>

            <div v-if="!inline">
                <label class="lbl">{{ $t('script.file') }}</label>
                <select :value="action.config.script" class="inp font-mono text-xs" @change="patch({ script: $event.target.value })">
                    <option value="">{{ $t('script.choose') }}</option>
                    <option v-for="file in info?.scripts ?? []" :key="file" :value="file">{{ file }}</option>
                    <option
                        v-if="action.config.script && !(info?.scripts ?? []).includes(action.config.script)"
                        :value="action.config.script"
                    >
                        {{ action.config.script }} — {{ $t('script.missing') }}
                    </option>
                </select>
                <p v-if="info" class="hint">{{ $t('script.dirHint', { dir: info.directory }) }}</p>
            </div>

            <div v-else>
                <div class="flex items-center gap-2">
                    <label class="lbl mb-0">{{ $t('script.code') }}</label>
                    <button class="ml-auto text-xs text-blue-600 hover:underline dark:text-blue-400" @click="insertExample">
                        {{ $t('script.insertExample') }}
                    </button>
                </div>
                <textarea
                    :value="action.config.code"
                    rows="12"
                    class="inp mt-1 font-mono text-xs"
                    :placeholder="$t('script.codePlaceholder')"
                    @input="patch({ code: $event.target.value })"
                ></textarea>
                <p class="hint">{{ $t('script.codeHint') }}</p>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2">
                    <label class="lbl">{{ $t('script.args') }}</label>
                    <input
                        :value="action.config.args"
                        class="inp font-mono text-xs"
                        placeholder="--order &quot;{{ json.order.id }}&quot;"
                        @input="patch({ args: $event.target.value })"
                    />
                </div>
                <div>
                    <label class="lbl">{{ $t('script.timeout') }}</label>
                    <input
                        :value="action.config.timeout"
                        type="number"
                        min="1"
                        :max="info?.max_timeout ?? 300"
                        class="inp text-xs"
                        :placeholder="String(info?.default_timeout ?? 30)"
                        @input="patch({ timeout: Number($event.target.value) })"
                    />
                </div>
            </div>

            <div>
                <label class="lbl">{{ $t('script.stdin') }}</label>
                <select :value="stdinMode" class="inp text-xs" @change="patch({ stdin: $event.target.value })">
                    <option value="json">{{ $t('script.stdinJson') }}</option>
                    <option value="template">{{ $t('script.stdinTemplate') }}</option>
                    <option value="none">{{ $t('script.stdinNone') }}</option>
                </select>
                <textarea
                    v-if="stdinMode === 'template'"
                    :value="action.config.stdin_template"
                    rows="4"
                    class="inp mt-1 font-mono text-xs"
                    @input="patch({ stdin_template: $event.target.value })"
                ></textarea>
            </div>

            <div class="flex flex-wrap items-center gap-2 rounded-lg bg-slate-50 p-2 dark:bg-slate-800/40">
                <button class="btn-secondary text-xs" :disabled="testing" @click="run(false)">
                    {{ $t('script.dryRun') }}
                </button>
                <button class="btn-secondary text-xs" :disabled="testing" @click="run(true)">
                    {{ $t('script.runNow') }}
                </button>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $t('script.runNowHint') }}</span>
            </div>

            <div v-if="result" class="space-y-2 rounded-lg border border-slate-200 p-3 text-xs dark:border-slate-800">
                <p>
                    <strong>{{ $t('script.status') }}:</strong>
                    <span
                        :class="{
                            'text-emerald-600 dark:text-emerald-400': result.status === 'success',
                            'text-red-600 dark:text-red-400': result.status === 'failed',
                        }"
                    >{{ result.summary || result.status }}</span>
                    <span v-if="result.detail?.exit_code !== undefined" class="text-slate-500 dark:text-slate-400">
                        (exit {{ result.detail.exit_code }})
                    </span>
                </p>
                <p v-if="result.error" class="text-red-600 dark:text-red-400">{{ result.error }}</p>
                <div v-if="result.detail?.stdout">
                    <p class="lbl">stdout</p>
                    <pre class="max-h-40 overflow-auto rounded bg-slate-900 p-2 font-mono text-[11px] text-slate-100">{{ result.detail.stdout }}</pre>
                </div>
                <div v-if="result.detail?.stderr">
                    <p class="lbl">stderr</p>
                    <pre class="max-h-40 overflow-auto rounded bg-slate-900 p-2 font-mono text-[11px] text-amber-200">{{ result.detail.stderr }}</pre>
                </div>
            </div>
        </div>
    </div>
</template>
