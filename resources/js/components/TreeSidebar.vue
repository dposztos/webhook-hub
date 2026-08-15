<script setup>
import { ref } from 'vue';
import { api } from '../api';
import TreeNode from './TreeNode.vue';

const props = defineProps({
    tree: { type: Object, required: true },
    selection: { type: Object, default: null },
    width: { type: Number, default: 288 },
});

const emit = defineEmits(['select', 'changed', 'notify', 'open-settings']);

const busy = ref(false);

const addRootGroup = async () => {
    const name = window.prompt('Új főcsoport neve (pl. Ügyfelek)');
    if (!name) return;

    busy.value = true;
    try {
        await api.createGroup({ name, parent_id: null });
        emit('changed');
        emit('notify', 'Csoport létrehozva', 'success');
    } catch (error) {
        emit('notify', error.message, 'error');
    } finally {
        busy.value = false;
    }
};

const addRootEndpoint = async () => {
    const name = window.prompt('Új URL neve (csoport nélkül)');
    if (!name) return;

    try {
        await api.createEndpoint({ name, group_id: null });
        emit('changed');
        emit('notify', 'URL létrehozva', 'success');
    } catch (error) {
        emit('notify', error.message, 'error');
    }
};
</script>

<template>
    <aside
        class="flex shrink-0 flex-col bg-white dark:bg-slate-900"
        :style="{ width: `${width}px` }"
    >
        <div class="flex items-center justify-between px-3 py-2.5">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Csoportok és URL-ek</h2>
            <div class="flex gap-1">
                <button
                    class="rounded px-1.5 py-0.5 text-lg leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    title="Új főcsoport"
                    :disabled="busy"
                    @click="addRootGroup"
                >
                    +
                </button>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-1.5 pb-3">
            <TreeNode
                v-for="group in tree.groups"
                :key="`g-${group.id}`"
                :node="group"
                :depth="0"
                :selection="selection"
                @select="emit('select', $event)"
                @changed="emit('changed')"
                @notify="(m, k) => emit('notify', m, k)"
                @open-settings="emit('open-settings', $event)"
            />

            <template v-if="tree.endpoints?.length">
                <p class="mt-3 px-2 text-[11px] uppercase tracking-wide text-slate-400 dark:text-slate-500">Csoport nélkül</p>
                <TreeNode
                    v-for="endpoint in tree.endpoints"
                    :key="`e-${endpoint.id}`"
                    :node="endpoint"
                    :depth="0"
                    :selection="selection"
                    @select="emit('select', $event)"
                    @changed="emit('changed')"
                    @notify="(m, k) => emit('notify', m, k)"
                    @open-settings="emit('open-settings', $event)"
                />
            </template>

            <p v-if="!tree.groups?.length && !tree.endpoints?.length" class="px-3 py-6 text-center text-sm text-slate-400 dark:text-slate-500">
                Még nincs semmi.<br />Hozz létre egy csoportot a + gombbal.
            </p>
        </div>

        <button
            class="border-t border-slate-200 px-3 py-2 text-left text-xs text-slate-500 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-400 dark:hover:bg-slate-800"
            @click="addRootEndpoint"
        >
            + URL csoport nélkül
        </button>
    </aside>
</template>
