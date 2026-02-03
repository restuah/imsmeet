<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">
                Welcome, {{ user?.name }}!
            </h1>
            <p class="text-gray-600">Ready to start or join a meeting?</p>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <router-link
                to="/meetings/new"
                class="card p-6 hover:shadow-md transition-shadow group"
            >
                <div class="flex items-center">
                    <div
                        class="w-12 h-12 bg-primary-100 rounded-lg flex items-center justify-center group-hover:bg-primary-200 transition-colors"
                    >
                        <svg
                            class="w-6 h-6 text-primary-600"
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
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900">
                            New Meeting
                        </h3>
                        <p class="text-sm text-gray-500">
                            Create and start a meeting
                        </p>
                    </div>
                </div>
            </router-link>

            <div
                class="card p-6 hover:shadow-md transition-shadow cursor-pointer"
                @click="showJoinModal = true"
            >
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
                                d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"
                            />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900">
                            Join Meeting
                        </h3>
                        <p class="text-sm text-gray-500">
                            Enter a meeting code
                        </p>
                    </div>
                </div>
            </div>

            <router-link
                to="/meetings"
                class="card p-6 hover:shadow-md transition-shadow group"
            >
                <div class="flex items-center">
                    <div
                        class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center group-hover:bg-purple-200 transition-colors"
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
                        <h3 class="text-lg font-semibold text-gray-900">
                            My Meetings
                        </h3>
                        <p class="text-sm text-gray-500">
                            View all your meetings
                        </p>
                    </div>
                </div>
            </router-link>
        </div>

        <!-- Recent Meetings -->
        <div class="card">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    Recent Meetings
                </h2>
            </div>
            <div v-if="loading" class="p-6 text-center">
                <div
                    class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full mx-auto"
                ></div>
            </div>
            <div
                v-else-if="meetings.length === 0"
                class="p-6 text-center text-gray-500"
            >
                No meetings yet. Create your first meeting!
            </div>
            <div v-else class="divide-y divide-gray-200">
                <div
                    v-for="meeting in meetings.slice(0, 5)"
                    :key="meeting.id"
                    class="px-6 py-4 hover:bg-gray-50 flex items-center justify-between"
                >
                    <div>
                        <h3 class="font-medium text-gray-900">
                            {{ meeting.title }}
                        </h3>
                        <p class="text-sm text-gray-500">
                            {{ formatDate(meeting.created_at) }}
                            <span
                                :class="statusClass(meeting.status)"
                                class="ml-2 px-2 py-0.5 text-xs rounded-full"
                            >
                                {{ meeting.status }}
                            </span>
                        </p>
                    </div>
                    <router-link
                        v-if="meeting.status !== 'ended'"
                        :to="`/meeting/${meeting.uuid}`"
                        class="btn-primary text-sm"
                    >
                        {{ meeting.status === "active" ? "Rejoin" : "Start" }}
                    </router-link>
                </div>
            </div>
        </div>

        <!-- Join Meeting Modal -->
        <div
            v-if="showJoinModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
        >
            <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    Join a Meeting
                </h3>
                <form @submit.prevent="handleJoinMeeting">
                    <div class="mb-4">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Meeting Code</label
                        >
                        <input
                            v-model="joinCode"
                            type="text"
                            class="input"
                            placeholder="Enter meeting code or link"
                            required
                        />
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button
                            type="button"
                            @click="showJoinModal = false"
                            class="btn-secondary"
                        >
                            Cancel
                        </button>
                        <button type="submit" class="btn-primary">Join</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../stores/auth";
import { useMeetingStore } from "../stores/meeting";

const router = useRouter();
const authStore = useAuthStore();
const meetingStore = useMeetingStore();

const user = computed(() => authStore.user);
const meetings = computed(() => meetingStore.meetings);
const loading = ref(true);

const showJoinModal = ref(false);
const joinCode = ref("");

onMounted(async () => {
    try {
        await meetingStore.fetchMeetings();
    } finally {
        loading.value = false;
    }
});

const handleJoinMeeting = () => {
    let uuid = joinCode.value.trim();

    // Extract UUID from URL if full URL is provided
    if (uuid.includes("/meeting/")) {
        uuid = uuid.split("/meeting/").pop();
    }
    if (uuid.includes("/join/")) {
        uuid = uuid.split("/join/").pop();
    }

    router.push(`/join/${uuid}`);
    showJoinModal.value = false;
    joinCode.value = "";
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

const statusClass = (status) => {
    const classes = {
        scheduled: "bg-yellow-100 text-yellow-800",
        active: "bg-green-100 text-green-800",
        ended: "bg-gray-100 text-gray-800",
    };
    return classes[status] || classes.scheduled;
};
</script>
