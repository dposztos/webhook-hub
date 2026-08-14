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
        notify(error.message ?? 'Ismeretlen hiba', 'error');
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

// A kijelölt elem friss adatai a fából (URL, számlálók) – a fa 5 másodpercenként frissül.
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
        filters.value.page = 1; // a figyelő tölti újra
        return;
    }

    loadMessages();
};

const openMessage = async (uuid) => {
    activeUuid.value = uuid;
    detail.value = await guard(() => api.message(uuid));
};

const refreshAll = async (silent = true) => {
    await Promise.all([loadTree(), loadMessages(silent)]);
};

// Szűrő változásakor vissza az első oldalra; lapozáskor csak újratöltünk.
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
        <header class="flex items-center gap-4 border-b border-slate-200 bg-white px-4 py-2.5">
            <div class="flex items-center gap-2 font-semibold text-slate-900">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-blue-600 text-sm text-white">W</span>
                Webhook Hub
            </div>

            <div class="ml-auto flex items-center gap-3 text-sm">
                <label class="flex cursor-pointer items-center gap-1.5 text-slate-600">
                    <input v-model="autoRefresh" type="checkbox" class="rounded border-slate-300" />
                    Élő frissítés
                </label>
                <button class="text-slate-500 hover:text-slate-800" @click="refreshAll(false)">Frissítés</button>
                <button class="text-slate-500 hover:text-slate-800" @click="logout">Kilépés</button>
            </div>
        </header>

        <div class="flex min-h-0 flex-1">
            <TreeSidebar
                :tree="tree"
                :selection="selection"
                @select="selectNode"
                @changed="loadTree"
                @notify="notify"
                @open-settings="panel = $event"
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

            <MessageDetail
                :detail="detail"
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
