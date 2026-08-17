<script setup>
import { useI18n } from '../i18n';

const { locale, setLocale, available, languageName } = useI18n();
</script>

<template>
    <!-- Hidden entirely while only one catalog is installed. -->
    <select
        v-if="available.length > 1"
        class="cursor-pointer rounded border-none bg-transparent py-1 pl-1 pr-5 text-sm text-slate-500 hover:text-slate-800 focus:outline-none dark:text-slate-400 dark:hover:text-slate-200"
        :value="locale"
        :title="$t('app.language')"
        :aria-label="$t('app.language')"
        @change="setLocale($event.target.value)"
    >
        <option v-for="code in available" :key="code" :value="code" class="text-slate-900 dark:text-slate-100">
            {{ languageName(code) }}
        </option>
    </select>
</template>
