<template>
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <router-link to="/dashboard" class="flex items-center">
                        <svg
                            class="w-8 h-8 text-primary-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
                            />
                        </svg>
                        <span class="ml-2 text-xl font-bold text-gray-900"
                            >IMSMeet Beta</span
                        >
                    </router-link>

                    <div class="hidden sm:ml-8 sm:flex sm:space-x-4">
                        <router-link
                            to="/dashboard"
                            class="nav-link"
                            active-class="active"
                        >
                            Dashboard
                        </router-link>
                        <router-link
                            to="/meetings"
                            class="nav-link"
                            active-class="active"
                        >
                            Meetings
                        </router-link>
                        <router-link
                            v-if="isAdmin"
                            to="/admin"
                            class="nav-link"
                            active-class="active"
                        >
                            Admin
                        </router-link>
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    <router-link to="/meetings/new" class="btn-primary">
                        <svg
                            class="w-4 h-4 mr-2"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                        New Meeting
                    </router-link>

                    <div class="relative" ref="dropdownRef">
                        <button
                            @click="showDropdown = !showDropdown"
                            class="flex items-center space-x-2 text-gray-700 hover:text-gray-900 focus:outline-none"
                        >
                            <div
                                class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center"
                            >
                                <span
                                    class="text-sm font-medium text-primary-700"
                                    >{{ userInitials }}</span
                                >
                            </div>
                            <span class="hidden md:block text-sm font-medium">{{
                                user?.name
                            }}</span>
                            <svg
                                class="w-4 h-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 9l-7 7-7-7"
                                />
                            </svg>
                        </button>

                        <div
                            v-if="showDropdown"
                            class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50"
                        >
                            <router-link
                                to="/profile"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                @click="showDropdown = false"
                            >
                                Profile
                            </router-link>
                            <hr class="my-1" />
                            <button
                                @click="handleLogout"
                                class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100"
                            >
                                Sign out
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../stores/auth";

const router = useRouter();
const authStore = useAuthStore();

const showDropdown = ref(false);
const dropdownRef = ref(null);

const user = computed(() => authStore.user);
const isAdmin = computed(() => authStore.isAdmin);

const userInitials = computed(() => {
    if (!user.value?.name) return "?";
    return user.value.name
        .split(" ")
        .map((n) => n[0])
        .join("")
        .toUpperCase()
        .slice(0, 2);
});

const handleLogout = async () => {
    await authStore.logout();
    router.push("/login");
};

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        showDropdown.value = false;
    }
};

onMounted(() => document.addEventListener("click", handleClickOutside));
onBeforeUnmount(() =>
    document.removeEventListener("click", handleClickOutside),
);
</script>

<style scoped>
.nav-link {
    @apply px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 rounded-md transition-colors;
}
.nav-link.active {
    @apply text-primary-600 bg-primary-50;
}
</style>
