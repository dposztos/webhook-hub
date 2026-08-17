<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { api } from '../api';
import TreeSidebar from './TreeSidebar.vue';
import MessageList from './MessageList.vue';
import MessageDetail from './MessageDetail.vue';
import RulesPanel from './RulesPanel.vue';
import EndpointSettings from './EndpointSettings.vue';
import GroupSettings from './GroupSettings.vue';
import ToastStack from './ToastStack.vue';
import ResizeHandle from './ResizeHandle.vue';
import LanguageSwitcher from './LanguageSwitcher.vue';
import { MODES, applyMode, storedMode, watchSystem } from '../theme';
import { t } from '../i18n';

// Panel widths: draggable, and remembered between visits.
const WIDTH_KEY = 'webhookhub-panels';
const savedWidths = (() => {
    try {
        return JSON.parse(localStorage.getItem(WIDTH_KEY)) ?? {};
    } catch {
        return {};
    }
})();

const sidebarWidth = ref(savedWidths.sidebar ?? 288);
const detailWidth = ref(savedWidths.detail ?? 608);

const saveWidths = () => {
    localStorage.setItem(
        WIDTH_KEY,
        JSON.stringify({ sidebar: sidebarWidth.value, detail: detailWidth.value }),
    );
};

const tree = ref({ groups: [], endpoints: [] });
const selection = ref(null); // { type: 'group'|'endpoint', id, name }
const messages = ref([]);
const meta = ref({ total: 0, current_page: 1, last_page: 1 });
const filters = ref({ q: '', method: '', only: '', page: 1 });
const activeUuid = ref(null);
const detail = ref(null);
const loading = ref(false);
const autoRefresh = ref(true);
const panel = ref(null); // 'rules' | 'endpoint' | 'group'
const toasts = ref([]);

let timer = null;

const notify = (message, kind = 'info') => {
    const id = Date.now() + Math.random();
    toasts.value.push({ id, message, kind });
    setTimeout(() => {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    }, kind === 'error' ? 6000 : 3000);
};

const guard = async (fn, successMessage) => {
    try {
        const result = await fn();
        if (successMessage) notify(successMessage, 'success');
        return result;
    } catch (error) {
        notify(error.message ?? t('common.unknownError'), 'error');
        throw error;
    }
};

const loadTree = async () => {
    tree.value = await api.tree();

    if (!selection.value) {
        const first = firstEndpoint(tree.value);
        if (first) selectNode({ type: 'endpoint', id: first.id, name: first.name });
    }
};

const firstEndpoint = (node) => {
    if (node.endpoints?.length) return node.endpoints[0];
    for (const group of node.groups ?? node.children ?? []) {
        const found = firstEndpoint(group);
        if (found) return found;
    }
    return null;
};

// Fresh data for the selected node (URL, counters) — the tree refreshes every 5 seconds.
const selectedNode = computed(() => {
    if (!selection.value) return null;

    const walk = (node) => {
        for (const endpoint of node.endpoints ?? []) {
            if (selection.value.type === 'endpoint' && endpoint.id === selection.value.id) return endpoint;
        }

        for (const group of node.groups ?? node.children ?? []) {
            if (selection.value.type === 'group' && group.id === selection.value.id) return group;
            const found = walk(group);
            if (found) return found;
        }

        return null;
    };

    const node = walk(tree.value);

    return node ? { ...selection.value, ...node, type: selection.value.type } : selection.value;
});

const queryParams = computed(() => ({
    endpoint_id: selection.value?.type === 'endpoint' ? selection.value.id : null,
    group_id: selection.value?.type === 'group' ? selection.value.id : null,
    q: filters.value.q,
    method: filters.value.method,
    only: filters.value.only,
    page: filters.value.page,
    per_page: 50,
}));

const loadMessages = async (silent = false) => {
    if (!selection.value) return;
    if (!silent) loading.value = true;

    try {
        const response = await api.messages(queryParams.value);
        messages.value = response.data;
        meta.value = response.meta;
    } catch (error) {
        if (!silent) notify(error.message, 'error');
    } finally {
        loading.value = false;
    }
};

const selectNode = (node) => {
    selection.value = node;
    activeUuid.value = null;
    detail.value = null;
    panel.value = null;

    if (filters.value.page !== 1) {
        filters.value.page = 1; // the watcher reloads
        return;
    }

    loadMessages();
};

const openMessage = async (uuid) => {
    activeUuid.value = uuid;
    detail.value = await guard(() => api.message(uuid));

    // Opening marks it read; reflect that on the list and the tree badge at once.
    const row = messages.value.find((message) => message.uuid === uuid);

    if (row && !row.read) {
        row.read = true;
        loadTree();
    }
};

const refreshAll = async (silent = true) => {
    await Promise.all([loadTree(), loadMessages(silent)]);
};

// A filter change jumps back to page one; paging just reloads.
watch(
    () => [filters.value.q, filters.value.method, filters.value.only],
    () => {
        if (filters.value.page !== 1) {
            filters.value.page = 1;
            return;
        }
        loadMessages();
    },
);

watch(() => filters.value.page, () => loadMessages());

onMounted(async () => {
    await guard(() => loadTree());
    timer = setInterval(() => {
        if (autoRefresh.value && !document.hidden) refreshAll(true);
    }, 5000);
});

onUnmounted(() => clearInterval(timer));

// Theme cycles: system → light → dark
const themeMode = ref(storedMode());

const nextTheme = () => {
    themeMode.value = MODES[(MODES.indexOf(themeMode.value) + 1) % MODES.length];
    applyMode(themeMode.value);
};

watchSystem(() => applyMode('system'));

const logout = () => {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/logout';
    form.innerHTML = `<input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]').content}">`;
    document.body.appendChild(form);
    form.submit();
};
</script>

<template>
    <div class="flex h-full flex-col">
        <header class="flex items-center gap-4 border-b border-slate-200 bg-white px-4 py-2.5 dark:bg-slate-900 dark:border-slate-800">
            <div class="flex items-center gap-2 font-semibold text-slate-900 dark:text-slate-100">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-600 text-sm text-white">W</span>
                Webhook Hub
            </div>

            <div class="ml-auto flex items-center gap-3 text-sm">
                <label class="flex cursor-pointer items-center gap-1.5 text-slate-600 dark:text-slate-400">
                    <input v-model="autoRefresh" type="checkbox" class="rounded border-slate-300 dark:border-slate-700" />
                    {{ $t('app.liveRefresh') }}
                </label>
                <button class="text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200" @click="refreshAll(false)">{{ $t('app.refresh') }}</button>

                <LanguageSwitcher />

                <button
                    class="rounded px-1.5 py-1 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    :title="$t('app.themeToggle', { mode: $t(`theme.${themeMode}`) })"
                    @click="nextTheme"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <!-- system: monitor -->
                        <path
                            v-if="themeMode === 'system'"
                            d="M3 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1h-4.5l.5 2h1.5a.5.5 0 0 1 0 1h-7a.5.5 0 0 1 0-1H8l.5-2H4a1 1 0 0 1-1-1zm1.5.5v7h11v-7z"
                        />
                        <!-- light: sun -->
                        <path
                            v-else-if="themeMode === 'light'"
                            d="M10 4a.75.75 0 0 1-.75-.75V2a.75.75 0 0 1 1.5 0v1.25A.75.75 0 0 1 10 4m0 12a.75.75 0 0 1 .75.75V18a.75.75 0 0 1-1.5 0v-1.25A.75.75 0 0 1 10 16m6-6a.75.75 0 0 1 .75-.75H18a.75.75 0 0 1 0 1.5h-1.25A.75.75 0 0 1 16 10m-12 0a.75.75 0 0 1-.75.75H2a.75.75 0 0 1 0-1.5h1.25A.75.75 0 0 1 4 10m10.24-4.24a.75.75 0 0 1 0-1.06l.88-.89a.75.75 0 1 1 1.07 1.07l-.89.88a.75.75 0 0 1-1.06 0m-9.55 9.55a.75.75 0 0 1 0-1.06l.89-.89a.75.75 0 0 1 1.06 1.07l-.88.88a.75.75 0 0 1-1.07 0m10.61 0a.75.75 0 0 1-1.06 0l-.89-.88a.75.75 0 0 1 1.06-1.07l.89.89a.75.75 0 0 1 0 1.06M5.76 5.76a.75.75 0 0 1-1.07 0l-.88-.88A.75.75 0 0 1 4.88 3.8l.88.89a.75.75 0 0 1 0 1.06M10 6.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7"
                        />
                        <!-- dark: moon -->
                        <path
                            v-else
                            d="M9.3 2.1a.75.75 0 0 1 .2.85 6 6 0 0 0 7.55 7.55.75.75 0 0 1 .95.95A7.5 7.5 0 1 1 8.45 1.9a.75.75 0 0 1 .85.2"
                        />
                    </svg>
                </button>
                <button class="text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200" @click="logout">{{ $t('app.logout') }}</button>
            </div>
        </header>

        <div class="flex min-h-0 flex-1">
            <TreeSidebar
                :tree="tree"
                :selection="selection"
                :width="sidebarWidth"
                @select="selectNode"
                @changed="loadTree"
                @notify="notify"
                @open-settings="panel = $event"
            />

            <ResizeHandle
                v-model:width="sidebarWidth"
                grows="right"
                :min="200"
                :max="640"
                @done="saveWidths"
            />

            <MessageList
                :messages="messages"
                :meta="meta"
                :loading="loading"
                :selection="selectedNode"
                :active-uuid="activeUuid"
                v-model:filters="filters"
                @open="openMessage"
                @rules="panel = 'rules'"
                @settings="panel = selection?.type === 'endpoint' ? 'endpoint' : 'group'"
                @changed="refreshAll(false)"
                @notify="notify"
            />

            <ResizeHandle
                v-if="detail"
                v-model:width="detailWidth"
                grows="left"
                :min="360"
                :max="1100"
                @done="saveWidths"
            />

            <MessageDetail
                :detail="detail"
                :width="detailWidth"
                @close="detail = null; activeUuid = null"
                @changed="refreshAll(false)"
                @notify="notify"
                @make-rule="panel = 'rules'"
            />
        </div>

        <RulesPanel
            v-if="panel === 'rules' && selection"
            :selection="selection"
            :sample-message="detail?.uuid ?? messages[0]?.uuid ?? null"
            @close="panel = null"
            @changed="loadTree"
            @notify="notify"
        />

        <EndpointSettings
            v-if="panel === 'endpoint' && selection?.type === 'endpoint'"
            :endpoint-id="selection.id"
            @close="panel = null"
            @changed="refreshAll(false)"
            @notify="notify"
        />

        <GroupSettings
            v-if="panel === 'group' && selection?.type === 'group'"
            :group="selection"
            @close="panel = null"
            @changed="refreshAll(false)"
            @notify="notify"
        />

        <ToastStack :toasts="toasts" />
    </div>
</template>
