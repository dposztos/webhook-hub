import { reactive } from 'vue';

/**
 * A fában húzott elem közös állapota (a TreeNode példányok ezen osztoznak).
 * mode: 'into' = a cél csoportba, 'before' = a cél elé, azonos szinten.
 */
export const drag = reactive({
    node: null,
    parentId: null,
    overKey: null,
    mode: null,
});

export const dragKey = (node) => `${node.type}-${node.id}`;

export function startDrag(node, parentId) {
    drag.node = node;
    drag.parentId = parentId ?? null;
}

export function endDrag() {
    drag.node = null;
    drag.parentId = null;
    drag.overKey = null;
    drag.mode = null;
}

/** Saját magába vagy a saját részfájába nem lehet ejteni. */
export function isInsideDragged(targetType, targetId) {
    const source = drag.node;
    if (!source) return false;
    if (source.type === targetType && source.id === targetId) return true;
    if (source.type !== 'group') return false;

    const walk = (group) => {
        if (targetType === 'group' && group.id === targetId) return true;
        if (targetType === 'endpoint' && (group.endpoints ?? []).some((e) => e.id === targetId)) return true;
        return (group.children ?? []).some(walk);
    };

    return (source.children ?? []).some(walk) || (source.endpoints ?? []).some((e) => e.type === targetType && e.id === targetId);
}

/** Hány webhook URL címe változik meg az áthelyezéssel. */
export function affectedUrls(node) {
    if (!node) return 0;
    if (node.type === 'endpoint') return 1;

    const count = (group) =>
        (group.endpoints?.length ?? 0) + (group.children ?? []).reduce((sum, child) => sum + count(child), 0);

    return count(node);
}
