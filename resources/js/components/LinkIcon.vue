<script setup lang="ts">
import { computed } from 'vue';
import { LINK_ICONS } from '../utils/linkIcons';
import type { LinkPageIconName } from '../types/linkPage';

/**
 * A pictogram of the module's own set, drawn inline in the text colour. It is decoration: the
 * button that holds it carries the accessible name.
 */
const props = defineProps<{ name: LinkPageIconName }>();

// A name outside the set (an older page with a newer server) draws nothing instead of failing
const icon = computed(() => (props.name in LINK_ICONS ? LINK_ICONS[props.name] : null));
</script>

<template>
    <svg
        v-if="icon"
        :viewBox="icon.viewBox"
        fill="currentColor"
        aria-hidden="true"
        focusable="false"
        class="link-page-icon"
        data-testid="link-page-icon"
        :data-icon="name"
    >
        <path
            v-for="(shape, index) in icon.shapes"
            :key="index"
            :d="shape.d"
            :fill-rule="shape.evenodd ? 'evenodd' : undefined"
            :clip-rule="shape.evenodd ? 'evenodd' : undefined"
        />
    </svg>
</template>
