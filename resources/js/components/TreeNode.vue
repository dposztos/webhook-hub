<script setup>
import { computed, ref } from 'vue';
import { api } from '../api';
import { copyText, relativeTime } from '../format';
import { affectedUrls, drag, dragKey, endDrag, isInsideDragged, startDrag } from '../drag';

const props = defineProps({
    node: { type: Object, required: true },
    depth: { type: Number, default: 0 },
    selection: { type: Object, default: null },
    parentId: { type: Number, default: null },
    index: { type: Number, default: 0 },
});

const emit = defineEmits(['select', 'changed', 'notify', 'open-settings']);

const open = ref(true);
const menu = ref(false);

const isGroup = computed(() => props.node.type === 'group');
const selected = computed(
    () => props.selection?.type === props.node.type && props.selection?.id === props.node.id,
);

const select = () =>
    emit('select', { type: props.node.type, id: props.node.id, name: props.node.name, url: props.node.url });

// --- Húzd és ejtsd: átszervezés a fában ---
const key = computed(() => dragKey(props.node));
const isDragged = computed(() => drag.node && dragKey(drag.node) === key.value);
const dropMode = computed(() => (drag.overKey === key.value ? drag.mode : null));

const onDragStart = (event) => {
    startDrag({ ...props.node, parentId: props.parentId }, props.parentId);
    event.dataTransfer.effectAllowed = 'move';
    // A böngésző csak akkor indít húzást, ha van adat a vágólapon
    event.dataTransfer.setData('text/plain', props.node.name);
};

const onDragOver = (event) => {
    if (!drag.node || isDragged.value) return;
    if (isInsideDragged(props.node.type, props.node.id)) return;

    // A sor „elnyeli” az eseményt, különben a mögötte lévő gyökér-ejtőzóna
    // (TreeSidebar) törölné a most beállított célpontot.
    event.stopPropagation();

    const box = event.currentTarget.getBoundingClientRect();
    const nearTop = event.clientY - box.top < box.height * 0.4;

    // Csoport közepére ejtve bele kerül, a felső sávra ejtve elé
    drag.overKey = key.value;
    drag.mode = isGroup.value && !nearTop ? 'into' : 'before';

    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
};

const onDragLeave = () => {
    if (drag.overKey === key.value) {
        drag.overKey = null;
        drag.mode = null;
    }
};

const onDrop = async (event) => {
    event.preventDefault();
    event.stopPropagation();

    const source = drag.node;
    const mode = dropMode.value;
    endDrag();

    if (!source || !mode) return;

    const targetParent = mode === 'into' ? props.node.id : props.parentId;
    const position = mode === 'into' ? null : props.index;

    await moveNode(source, targetParent, position);
};

const moveNode = async (source, targetParent, position) => {
    const sameParent = (source.parentId ?? null) === (targetParent ?? null);

    if (!sameParent) {
        const count = affectedUrls(source);
        const message = count
            ? `Az áthelyezés ${count} webhook URL címét megváltoztatja (az útvonalban a csoportok neve szerepel).\n\nFolytatod?`
            : 'Áthelyezed?';

        if (!window.confirm(message)) return;
    }

    try {
        const result = await api.move({
            type: source.type,
            id: source.id,
            parent_id: targetParent,
            position,
        });

        emit('changed');
        emit(
            'notify',
            result.slug_changed
                ? `Áthelyezve – a névütközés miatt az útvonal "${result.slug}" lett`
                : 'Áthelyezve',
            'success',
        );
    } catch (error) {
        emit('notify', error.message, 'error');
    }
};

const addSubgroup = async () => {
    const name = window.prompt(`Új alcsoport a(z) "${props.node.name}" alatt`);
    if (!name) return;
    await run(() => api.createGroup({ name, parent_id: props.node.id }), 'Alcsoport létrehozva');
};

const addEndpoint = async () => {
    const name = window.prompt(`Új URL a(z) "${props.node.name}" csoportban (pl. Rendelések)`);
    if (!name) return;
    await run(() => api.createEndpoint({ name, group_id: props.node.id }), 'URL létrehozva');
};

const rename = async () => {
    const name = window.prompt('Új név', props.node.name);
    if (!name || name === props.node.name) return;
    await run(
        () => (isGroup.value ? api.updateGroup(props.node.id, { name }) : api.updateEndpoint(props.node.id, { name })),
        'Átnevezve',
    );
};

const remove = async () => {
    const what = isGroup.value ? 'a csoportot az összes alcsoporttal, URL-lel és üzenettel' : 'az URL-t az összes üzenetével';
    if (!window.confirm(`Biztosan törlöd ${what} együtt?\n\n"${props.node.name}"`)) return;
    await run(
        () => (isGroup.value ? api.deleteGroup(props.node.id) : api.deleteEndpoint(props.node.id)),
        'Törölve',
    );
};

const copyUrl = async () => {
    await copyText(props.node.url);
    emit('notify', 'URL a vágólapon', 'success');
    menu.value = false;
};

const run = async (fn, message) => {
    menu.value = false;
    try {
        await fn();
        emit('changed');
        emit('notify', message, 'success');
    } catch (error) {
        emit('notify', error.message, 'error');
    }
};
</script>

<template>
    <div>
        <div
            class="group relative flex items-center gap-1 rounded-lg px-1.5 py-1 text-sm"
            :class="[
                selected ? 'bg-blue-50 text-blue-900 dark:bg-blue-950 dark:text-blue-100' : 'text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800',
                isDragged ? 'opacity-40' : '',
                dropMode === 'into' ? 'ring-2 ring-blue-400' : '',
                dropMode === 'before' ? 'border-t-2 border-blue-500' : '',
            ]"
            :style="{ paddingLeft: `${depth * 12 + 6}px` }"
            draggable="true"
            @dragstart.stop="onDragStart"
            @dragend="endDrag"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @drop="onDrop"
        >
            <button
                v-if="isGroup"
                class="w-4 shrink-0 text-slate-400 hover:text-slate-700 dark:text-slate-500 dark:hover:text-slate-200"
                @click.stop="open = !open"
            >
                {{ open ? '▾' : '▸' }}
            </button>
            <span v-else class="w-4 shrink-0"></span>

            <button class="flex min-w-0 flex-1 items-center gap-1.5 text-left" @click="select">
                <svg
                    class="h-3.5 w-3.5 shrink-0"
                    :class="isGroup ? 'text-amber-500' : 'text-sky-500'"
                    viewBox="0 0 16 16"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path
                        v-if="isGroup"
                        d="M1.5 3.5A1.5 1.5 0 0 1 3 2h3l1.5 1.5H13A1.5 1.5 0 0 1 14.5 5v6A1.5 1.5 0 0 1 13 12.5H3A1.5 1.5 0 0 1 1.5 11z"
                    />
                    <path
                        v-else
                        d="M6.5 9.5a2.5 2.5 0 0 0 3.54 0l2-2a2.5 2.5 0 1 0-3.54-3.54l-.7.7 1.06 1.07.7-.71a1 1 0 1 1 1.42 1.42l-2 2a1 1 0 0 1-1.42 0zm3 -3a2.5 2.5 0 0 0-3.54 0l-2 2A2.5 2.5 0 1 0 7.5 12l.7-.7-1.06-1.07-.7.71a1 1 0 1 1-1.42-1.42l2-2a1 1 0 0 1 1.42 0z"
                    />
                </svg>
                <span class="truncate" :class="{ 'font-medium': selected }">{{ node.name }}</span>

                <span
                    v-if="!isGroup && node.messages_count"
                    class="ml-auto shrink-0 rounded-full bg-slate-100 px-1.5 text-[11px] text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                    :title="`Utolsó üzenet: ${relativeTime(node.last_message_at)}`"
                >
                    {{ node.messages_count }}
                </span>
                <span
                    v-if="node.rules_count"
                    class="shrink-0 text-[11px] text-emerald-600 dark:text-emerald-400"
                    :title="`${node.rules_count} aktív szabály`"
                >
                    ⚡ {{ node.rules_count }}
                </span>
                <span v-if="!isGroup && node.enabled === false" class="shrink-0 text-[11px] text-red-500 dark:text-red-400">szünetel</span>
            </button>

            <button
                class="shrink-0 rounded px-1 text-slate-400 opacity-0 group-hover:opacity-100 hover:bg-slate-200 dark:text-slate-500 dark:hover:bg-slate-700"
                @click.stop="menu = !menu"
            >
                ⋯
            </button>

            <div
                v-if="menu"
                class="absolute right-1 top-7 z-20 w-52 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg dark:bg-slate-900 dark:border-slate-800"
                @mouseleave="menu = false"
            >
                <template v-if="isGroup">
                    <button class="menu-item" @click="addSubgroup">Új alcsoport…</button>
                    <button class="menu-item" @click="addEndpoint">Új URL a csoportban…</button>
                </template>
                <template v-else>
                    <button class="menu-item" @click="copyUrl">URL másolása</button>
                    <button class="menu-item" @click="menu = false; select(); emit('open-settings', 'endpoint')">
                        Beállítások…
                    </button>
                </template>
                <button class="menu-item" @click="menu = false; select(); emit('open-settings', 'rules')">
                    Szabályok…
                </button>
                <button class="menu-item" @click="rename">Átnevezés…</button>
                <button class="menu-item text-red-600 dark:text-red-400" @click="remove">Törlés…</button>
            </div>
        </div>

        <div v-if="isGroup && open">
            <TreeNode
                v-for="(child, childIndex) in node.children"
                :key="`g-${child.id}`"
                :node="child"
                :depth="depth + 1"
                :selection="selection"
                :parent-id="node.id"
                :index="childIndex"
                @select="emit('select', $event)"
                @changed="emit('changed')"
                @notify="(m, k) => emit('notify', m, k)"
                @open-settings="emit('open-settings', $event)"
            />
            <TreeNode
                v-for="(endpoint, endpointIndex) in node.endpoints"
                :key="`e-${endpoint.id}`"
                :node="endpoint"
                :depth="depth + 1"
                :selection="selection"
                :parent-id="node.id"
                :index="endpointIndex"
                @select="emit('select', $event)"
                @changed="emit('changed')"
                @notify="(m, k) => emit('notify', m, k)"
                @open-settings="emit('open-settings', $event)"
            />
        </div>
    </div>
</template>

