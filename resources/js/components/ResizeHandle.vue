<script setup>
import { ref } from 'vue';

const props = defineProps({
    // 'right': dragging right grows the panel to the right of the handle (left sidebar)
    // 'left':  dragging right grows the panel to the left of the handle (right panel)
    grows: { type: String, default: 'right' },
    width: { type: Number, required: true },
    min: { type: Number, default: 200 },
    max: { type: Number, default: 900 },
});

const emit = defineEmits(['update:width', 'done']);

const dragging = ref(false);

const start = (event) => {
    event.preventDefault();
    dragging.value = true;

    const startX = event.clientX;
    const startWidth = props.width;
    const direction = props.grows === 'right' ? 1 : -1;

    const move = (e) => {
        const next = startWidth + direction * (e.clientX - startX);
        emit('update:width', Math.min(props.max, Math.max(props.min, Math.round(next))));
    };

    const stop = () => {
        dragging.value = false;
        document.body.classList.remove('select-none', 'cursor-col-resize');
        window.removeEventListener('mousemove', move);
        window.removeEventListener('mouseup', stop);
        emit('done');
    };

    document.body.classList.add('select-none', 'cursor-col-resize');
    window.addEventListener('mousemove', move);
    window.addEventListener('mouseup', stop);
};

const reset = () => {
    emit('update:width', props.grows === 'right' ? 288 : 608);
    emit('done');
};
</script>

<template>
    <div
        class="group relative w-px shrink-0 cursor-col-resize bg-slate-200 dark:bg-slate-800"
        :title="$t('resize.title')"
        @mousedown="start"
        @dblclick="reset"
    >
        <!-- Easy to grab with the mouse, but visually a hairline -->
        <div
            class="absolute inset-y-0 -left-1 -right-1 transition-colors group-hover:bg-blue-400/40"
            :class="{ 'bg-blue-500/60': dragging }"
        ></div>
    </div>
</template>
