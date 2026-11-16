<script setup lang="ts">
import SiteBlock from '@/components/layout/SiteBlock.vue';
import type { LinkPageBlock } from '../types/linkPage';

/**
 * One site block of another module (link-page#48) in a card of the page. A block the core cannot
 * draw leaves its card empty, and module.css hides the whole section then. `index` is the block's
 * place in the page's whole list, so ids stay unique whether it goes before or after the calendar.
 */
defineProps<{ item: LinkPageBlock; index: number }>();
</script>

<template>
    <section
        :aria-labelledby="item.title ? `link-page-block-${index}` : undefined"
        :aria-label="item.title ? undefined : (item.label ?? undefined)"
        class="link-page-block"
        data-testid="link-page-block"
    >
        <h2 v-if="item.title" :id="`link-page-block-${index}`" class="mb-3 text-lg font-semibold">
            {{ item.title }}
        </h2>
        <div class="link-page-block__card p-4 sm:p-5">
            <SiteBlock :block="item.block" placement="link_page" />
        </div>
    </section>
</template>
