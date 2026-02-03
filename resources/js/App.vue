<template>
    <div id="app" class="min-h-screen">
        <Navbar v-if="showNavbar" />
        <main>
            <router-view />
        </main>
        <Toast />
    </div>
</template>

<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";
import Navbar from "./components/Navbar.vue";
import Toast from "./components/Toast.vue";

const route = useRoute();

const showNavbar = computed(() => {
    const hideNavbarRoutes = ["meeting.room"];
    return !hideNavbarRoutes.includes(route.name);
});
</script>
