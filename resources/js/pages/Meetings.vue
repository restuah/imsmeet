<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">My Meetings</h1>
                <p class="text-gray-600">
                    Manage your scheduled and past meetings
                </p>
            </div>
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
        </div>

        <!-- Filters -->
        <div class="card mb-6 p-4">
            <div class="flex flex-wrap gap-4">
                <button
                    v-for="filter in filters"
                    :key="filter.value"
                    @click="activeFilter = filter.value"
                    :class="[
                        'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                        activeFilter === filter.value
                            ? 'bg-primary-600 text-white'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                    ]"
                >
                    {{ filter.label }}
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="text-center py-12">
            <div
                class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full mx-auto"
            ></div>
            <p class="mt-4 text-gray-500">Loading meetings...</p>
        </div>

        <!-- Empty State -->
        <div
            v-else-if="filteredMeetings.length === 0"
            class="card p-12 text-center"
        >
            <svg
                class="w-16 h-16 text-gray-300 mx-auto mb-4"
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
            <h3 class="text-lg font-medium text-gray-900 mb-2">
                No meetings found
            </h3>
            <p class="text-gray-500 mb-4">
                {{
                    activeFilter === "all"
                        ? "You haven't created any meetings yet."
                        : `No ${activeFilter} meetings.`
                }}
            </p>
            <router-link to="/meetings/new" class="btn-primary">
                Create your first meeting
            </router-link>
        </div>

        <!-- Meetings List -->
        <div v-else class="grid gap-4">
            <div
                v-for="meeting in filteredMeetings"
                :key="meeting.id"
                class="card p-6 hover:shadow-md transition-shadow"
            >
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center space-x-3">
                            <h3 class="text-lg font-semibold text-gray-900">
                                {{ meeting.title }}
                            </h3>
                            <span
                                :class="statusClass(meeting.status)"
                                class="px-2 py-0.5 text-xs font-medium rounded-full"
                            >
                                {{ meeting.status }}
                            </span>
                        </div>
                        <p
                            v-if="meeting.description"
                            class="mt-1 text-sm text-gray-500 line-clamp-2"
                        >
                            {{ meeting.description }}
                        </p>
                        <div
                            class="mt-3 flex items-center space-x-4 text-sm text-gray-500"
                        >
                            <span class="flex items-center">
                                <svg
                                    class="w-4 h-4 mr-1"
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
                                {{
                                    formatDate(
                                        meeting.scheduled_at ||
                                            meeting.created_at,
                                    )
                                }}
                            </span>
                            <span class="flex items-center">
                                <svg
                                    class="w-4 h-4 mr-1"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                                    />
                                </svg>
                                {{
                                    meeting.active_participants_count || 0
                                }}
                                participants
                            </span>
                            <span
                                v-if="meeting.password"
                                class="flex items-center text-yellow-600"
                            >
                                <svg
                                    class="w-4 h-4 mr-1"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                    />
                                </svg>
                                Protected
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2 ml-4">
                        <button
                            @click="copyLink(meeting)"
                            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg"
                            title="Copy link"
                        >
                            <svg
                                class="w-5 h-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                />
                            </svg>
                        </button>
                        <router-link
                            v-if="meeting.status !== 'ended'"
                            :to="`/meeting/${meeting.uuid}`"
                            class="btn-primary"
                        >
                            {{
                                meeting.status === "active" ? "Rejoin" : "Start"
                            }}
                        </router-link>
                        <button
                            v-if="meeting.status === 'scheduled'"
                            @click="deleteMeeting(meeting)"
                            class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg"
                            title="Delete"
                        >
                            <svg
                                class="w-5 h-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useMeetingStore } from "../stores/meeting";

const meetingStore = useMeetingStore();

const loading = ref(true);
const activeFilter = ref("all");

const filters = [
    { label: "All", value: "all" },
    { label: "Scheduled", value: "scheduled" },
    { label: "Active", value: "active" },
    { label: "Ended", value: "ended" },
];

const meetings = computed(() => meetingStore.meetings);

const filteredMeetings = computed(() => {
    if (activeFilter.value === "all") return meetings.value;
    return meetings.value.filter((m) => m.status === activeFilter.value);
});

onMounted(async () => {
    try {
        await meetingStore.fetchMeetings();
    } finally {
        loading.value = false;
    }
});

const formatDate = (date) => {
    return new Date(date).toLocaleDateString("en-US", {
        weekday: "short",
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

const copyLink = (meeting) => {
    const link = `${window.location.origin}/join/${meeting.uuid}`;
    navigator.clipboard.writeText(link);
    window.$toast?.success("Meeting link copied to clipboard");
};

const deleteMeeting = async (meeting) => {
    if (!confirm("Are you sure you want to delete this meeting?")) return;

    try {
        await meetingStore.deleteMeeting(meeting.id);
        window.$toast?.success("Meeting deleted");
    } catch (error) {
        window.$toast?.error("Failed to delete meeting");
    }
};
</script>
