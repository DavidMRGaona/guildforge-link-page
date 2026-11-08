<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import LinkIcon from './LinkIcon.vue';
import type { LinkPageLink } from '../types/linkPage';

/**
 * A button for one of the association's own links. It inherits the page's button style, shape
 * and colours (`link-page-button`); a featured one is bigger and gets a soft ring that stays
 * still for visitors who prefer less motion.
 */
defineProps<{ link: LinkPageLink }>();

const { t } = useI18n();
</script>

<template>
    <a
        :href="link.url"
        :target="link.external ? '_blank' : undefined"
        :rel="link.external ? 'noopener noreferrer' : undefined"
        :data-link-id="link.linkId"
        :class="[
            'link-page-button flex items-center gap-3 px-4',
            link.featured ? 'link-page-button--featured min-h-20 py-4 text-lg' : 'min-h-14 py-3',
        ]"
        data-testid="link-page-link"
    >
        <LinkIcon v-if="link.icon" :name="link.icon" class="size-6 shrink-0" />
        <span class="flex min-w-0 flex-1 flex-col justify-center">
            <span class="flex flex-wrap items-center gap-x-2 gap-y-1 font-semibold">
                <span
                    v-if="link.featured"
                    class="link-page-button__badge rounded-full px-2 py-0.5 text-xs font-semibold"
                    data-testid="link-page-link-badge"
                >
                    {{ t('linkPage.featured') }}
                </span>
                <span class="min-w-0 break-words">{{ link.title }}</span>
            </span>
            <span
                v-if="link.description"
                class="link-page-button__meta mt-0.5 break-words text-sm font-normal"
                data-testid="link-page-link-description"
            >
                {{ link.description }}
            </span>
        </span>
        <span v-if="link.external" class="sr-only">{{ t('linkPage.opensInNewTab') }}</span>
    </a>
</template>
