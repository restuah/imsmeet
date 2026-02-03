<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
            <p class="text-gray-600">System overview and management</p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="card p-6">
                <div class="flex items-center">
                    <div
                        class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center"
                    >
                        <svg
                            class="w-6 h-6 text-blue-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Total Users</p>
                        <p class="text-2xl font-bold text-gray-900">
                            {{ stats.total_users }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="card p-6">
                <div class="flex items-center">
                    <div
                        class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center"
                    >
                        <svg
                            class="w-6 h-6 text-green-600"
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
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Active Meetings</p>
                        <p class="text-2xl font-bold text-gray-900">
                            {{ stats.active_meetings }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="card p-6">
                <div class="flex items-center">
                    <div
                        class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center"
                    >
                        <svg
                            class="w-6 h-6 text-purple-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                            />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Total Meetings</p>
                        <p class="text-2xl font-bold text-gray-900">
                            {{ stats.total_meetings }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="card p-6">
                <div class="flex items-center">
                    <div
                        class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center"
                    >
                        <svg
                            class="w-6 h-6 text-yellow-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Today's Meetings</p>
                        <p class="text-2xl font-bold text-gray-900">
                            {{ stats.total_meetings_today }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <router-link
                to="/admin/users"
                class="card p-6 hover:shadow-md transition-shadow group"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            User Management
                        </h3>
                        <p class="text-gray-500 text-sm mt-1">
                            Manage users, roles, and permissions
                        </p>
                    </div>
                    <svg
                        class="w-6 h-6 text-gray-400 group-hover:text-primary-600 transition-colors"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 5l7 7-7 7"
                        />
                    </svg>
                </div>
            </router-link>

            <router-link
                to="/admin/meetings"
                class="card p-6 hover:shadow-md transition-shadow group"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            Meeting Management
                        </h3>
                        <p class="text-gray-500 text-sm mt-1">
                            View and manage all meetings
                        </p>
                    </div>
                    <svg
                        class="w-6 h-6 text-gray-400 group-hover:text-primary-600 transition-colors"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 5l7 7-7 7"
                        />
                    </svg>
                </div>
            </router-link>
        </div>

        <!-- Users by Role -->
        <div class="card p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                Users by Role
            </h3>
            <div class="flex space-x-8">
                <div
                    v-for="(count, role) in stats.users_by_role"
                    :key="role"
                    class="text-center"
                >
                    <p class="text-3xl font-bold text-gray-900">{{ count }}</p>
                    <p class="text-sm text-gray-500 capitalize">{{ role }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import api from "../../services/api";

const stats = ref({
    total_users: 0,
    total_meetings: 0,
    active_meetings: 0,
    total_meetings_today: 0,
    users_by_role: {},
    meetings_by_status: {},
});

const loading = ref(true);

onMounted(async () => {
    try {
        const response = await api.get("/admin/statistics");
        stats.value = response.data;
    } catch (e) {
        console.error("Failed to fetch stats:", e);
    } finally {
        loading.value = false;
    }
});
</script>
