<script setup>
defineProps({
    toasts: { type: Array, default: () => [] },
});

const toastClass = (kind) =>
    ({
        success: 'bg-emerald-600',
        error: 'bg-red-600',
    })[kind] ?? 'bg-slate-800';
</script>

<template>
    <div class="pointer-events-none fixed bottom-4 right-4 z-50 flex flex-col gap-2">
        <div
            v-for="toast in toasts"
            :key="toast.id"
            class="max-w-sm rounded-lg px-4 py-2 text-sm text-white shadow-lg"
            :class="toastClass(toast.kind)"
        >
            {{ toast.message }}
        </div>
    </div>
</template>
