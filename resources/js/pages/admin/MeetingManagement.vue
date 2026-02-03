<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Meeting Management</h1>
            <p class="text-gray-600">View and manage all system meetings</p>
        </div>

        <div class="card p-4 mb-6">
            <div class="flex flex-wrap gap-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search meetings..."
                    class="input w-64"
                    @input="debouncedSearch"
                />
                <select
                    v-model="statusFilter"
                    class="input w-40"
                    @change="fetchMeetings"
                >
                    <option value="">All Status</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="active">Active</option>
                    <option value="ended">Ended</option>
                </select>
            </div>
        </div>

        <div class="card overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                        >
                            Meeting
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                        >
                            Host
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                        >
                            Status
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                        >
                            Participants
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                        >
                            Created
                        </th>
                        <th
                            class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase"
                        >
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr
                        v-for="meeting in meetings"
                        :key="meeting.id"
                        class="hover:bg-gray-50"
                    >
                        <td class="px-6 py-4">
                            <p class="text-sm font-medium text-gray-900">
                                {{ meeting.title }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ meeting.uuid }}
                            </p>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ meeting.host?.name || "Unknown" }}
                        </td>
                        <td class="px-6 py-4">
                            <span
                                :class="statusClass(meeting.status)"
                                class="px-2 py-1 text-xs font-medium rounded-full"
                                >{{ meeting.status }}</span
                            >
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ meeting.participants_count || 0 }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ formatDate(meeting.created_at) }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <router-link
                                v-if="meeting.status !== 'ended'"
                                :to="`/meeting/${meeting.uuid}`"
                                class="text-primary-600 hover:text-primary-800 mr-3"
                                >Join</router-link
                            >
                            <button
                                v-if="meeting.status === 'active'"
                                @click="endMeeting(meeting)"
                                class="text-red-600 hover:text-red-800"
                            >
                                End
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div
                v-if="meetings.length === 0"
                class="p-8 text-center text-gray-500"
            >
                No meetings found
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import api from "../../services/api";

const meetings = ref([]);
const search = ref("");
const statusFilter = ref("");
let searchTimeout = null;

onMounted(() => fetchMeetings());

const fetchMeetings = async () => {
    try {
        const response = await api.get("/admin/meetings", {
            params: { search: search.value, status: statusFilter.value },
        });
        meetings.value = response.data.data;
    } catch (e) {
        console.error(e);
    }
};

const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(fetchMeetings, 300);
};

const endMeeting = async (meeting) => {
    if (!confirm("End this meeting for all participants?")) return;
    try {
        await api.post(`/meetings/${meeting.id}/end`);
        await fetchMeetings();
        window.$toast?.success("Meeting ended");
    } catch (e) {
        window.$toast?.error("Failed to end meeting");
    }
};

const formatDate = (date) =>
    new Date(date).toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
const statusClass = (status) =>
    ({
        scheduled: "bg-yellow-100 text-yellow-800",
        active: "bg-green-100 text-green-800",
        ended: "bg-gray-100 text-gray-800",
    })[status] || "bg-gray-100 text-gray-800";
</script>
