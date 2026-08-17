import { reactive } from 'vue';

/**
 * Shared state for the node being dragged (every TreeNode instance reads it).
 * mode: 'into' = into the target group, 'before' = above the target, same level.
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

/** A node cannot be dropped into itself or into its own subtree. */
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

/** How many webhook URLs change address as a result of the move. */
export function affectedUrls(node) {
    if (!node) return 0;
    if (node.type === 'endpoint') return 1;

    const count = (group) =>
        (group.endpoints?.length ?? 0) + (group.children ?? []).reduce((sum, child) => sum + count(child), 0);

    return count(node);
}
