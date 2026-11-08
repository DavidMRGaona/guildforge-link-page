<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import LinkIcon from './LinkIcon.vue';
import type { LinkPageSocialLink } from '../types/linkPage';

/**
 * The row of social icons. Each one is a 44 px touch target with the page's button style, and
 * its accessible name is the network (plus the new-tab notice for web addresses).
 */
defineProps<{ links: LinkPageSocialLink[] }>();

const { t } = useI18n();
</script>

<template>
    <nav v-if="links.length > 0" :aria-label="t('linkPage.social')" data-testid="link-page-social">
        <ul class="flex list-none flex-wrap justify-center gap-3 p-0">
            <li v-for="link in links" :key="link.linkId">
                <a
                    :href="link.url"
                    :target="link.external ? '_blank' : undefined"
                    :rel="link.external ? 'noopener noreferrer' : undefined"
                    :data-link-id="link.linkId"
                    class="link-page-button link-page-social-button inline-flex size-11 items-center justify-center"
                    data-testid="link-page-social-link"
                >
                    <LinkIcon :name="link.icon" class="size-6" />
                    <span class="sr-only">
                        {{ link.label
                        }}<template v-if="link.external"
                            >, {{ t('linkPage.opensInNewTabShort') }}</template
                        >
                    </span>
                </a>
            </li>
        </ul>
    </nav>
</template>
