<template>
    <div>
        <ModuleTabBar v-if="currentModule" :tabs="currentModule.tabs" :basePath="currentModule.basePath" />
        <div class="p-4 lg:p-6">
            <RouterView />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { modules } from '../modules';
import ModuleTabBar from './ModuleTabBar.vue';

const route = useRoute();

const currentModule = computed(() => {
    const moduleId = route.meta.module || route.matched.find(r => r.meta.module)?.meta.module;
    return modules.find(m => m.id === moduleId);
});
</script>
