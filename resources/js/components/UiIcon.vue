<script setup>
defineProps({
    name: { type: String, required: true },
    size: { type: Number, default: 20 },
});
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        focusable="false"
    >
        <template v-if="name === 'compass'">
            <circle cx="12" cy="12" r="9" />
            <path d="m15.8 8.2-2.4 5.2-5.2 2.4 2.4-5.2 5.2-2.4Z" />
        </template>
        <template v-else-if="name === 'search'">
            <circle cx="10.8" cy="10.8" r="6.8" />
            <path d="m16 16 4.2 4.2" />
        </template>
        <template v-else-if="name === 'eye'">
            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
            <circle cx="12" cy="12" r="2.5" />
        </template>
        <template v-else-if="name === 'eye-off'">
            <path d="m3 3 18 18M10.6 6.2A10.8 10.8 0 0 1 12 6c6.5 0 10 6 10 6a15.2 15.2 0 0 1-3.1 3.7M6.2 6.8C3.5 8.4 2 12 2 12s3.5 6 10 6c1.1 0 2.1-.2 3-.5" />
            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
        </template>
        <template v-else-if="name === 'arrow'">
            <path d="M4 12h15" />
            <path d="m13 6 6 6-6 6" />
        </template>
        <template v-else-if="name === 'briefcase'">
            <rect x="3" y="7" width="18" height="13" rx="2" />
            <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18m-11 0v2h4v-2" />
        </template>
        <template v-else-if="name === 'graduation-cap'">
            <path d="m2 10 10-5 10 5-10 5-10-5Z" />
            <path d="M6 12v5c3.5 2.7 8.5 2.7 12 0v-5m4-2v6" />
        </template>
        <template v-else-if="name === 'users'">
            <path d="M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-4A4.5 4.5 0 0 0 3 18.5V20" />
            <circle cx="9.5" cy="7.5" r="3.5" />
            <path d="M17 4.3a3.5 3.5 0 0 1 0 6.8m4 8.9v-1.5a4.5 4.5 0 0 0-3.5-4.4" />
        </template>
        <template v-else-if="name === 'shield-check'">
            <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z" />
            <path d="m9 12 2 2 4-4" />
        </template>
        <template v-else-if="name === 'calendar'">
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M16 3v4M8 3v4M3 10h18" />
        </template>
        <template v-else-if="name === 'book'">
            <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v17H6.5A2.5 2.5 0 0 0 4 22V5.5Z" />
            <path d="M4 18.5A2.5 2.5 0 0 1 6.5 16H20" />
        </template>
        <template v-else-if="name === 'coins'">
            <ellipse cx="12" cy="5" rx="8" ry="3" />
            <path d="M4 5v5c0 1.7 3.6 3 8 3s8-1.3 8-3V5m-16 5v5c0 1.7 3.6 3 8 3 1.5 0 2.9-.2 4.1-.5M4 15v4c0 1.7 3.6 3 8 3" />
        </template>
        <template v-else-if="name === 'chart'">
            <path d="M3 20h18M6 16l4-5 4 3 5-8" />
            <path d="M15 6h4v4M6 20v-3m5 3v-6m5 6v-4" />
        </template>
        <template v-else-if="name === 'bolt'">
            <path d="m13 2-9 12h7l-1 8 10-13h-7l1-7Z" />
        </template>
        <template v-else-if="name === 'file'">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
            <path d="M14 2v6h6M8 13h8m-8 4h8" />
        </template>
        <template v-else-if="name === 'pin'">
            <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z" />
            <circle cx="12" cy="10" r="2.5" />
        </template>
        <template v-else-if="name === 'loader'">
            <path d="M12 3a9 9 0 1 0 9 9" />
        </template>
        <template v-else-if="name === 'bookmark'">
            <path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16Z" />
        </template>
        <template v-else-if="name === 'bookmark-check'">
            <path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16Z" />
            <path d="m9 11 2 2 4-4" />
        </template>
        <template v-else-if="name === 'trash'">
            <path d="M3 6h18m-2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
        </template>
        <template v-else-if="name === 'plus'">
            <path d="M5 12h14M12 5v14" />
        </template>
        <template v-else-if="name === 'check'">
            <path d="m5 12 5 5L20 7" />
        </template>
    </svg>
</template>
