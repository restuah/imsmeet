<template>
    <div
        class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4"
    >
        <div class="max-w-md w-full">
            <div v-if="loading" class="text-center">
                <div
                    class="animate-spin w-12 h-12 border-4 border-primary-600 border-t-transparent rounded-full mx-auto"
                ></div>
                <p class="mt-4 text-gray-600">Loading meeting info...</p>
            </div>

            <div v-else-if="error" class="card p-8 text-center">
                <svg
                    class="w-16 h-16 text-red-400 mx-auto mb-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                    />
                </svg>
                <h2 class="text-xl font-semibold text-gray-900 mb-2">
                    Meeting Not Found
                </h2>
                <p class="text-gray-600 mb-4">{{ error }}</p>
                <router-link to="/dashboard" class="btn-primary">
                    Go to Dashboard
                </router-link>
            </div>

            <div v-else class="card p-8">
                <div class="text-center mb-6">
                    <div
                        class="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4"
                    >
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
                    </div>
                    <h2 class="text-xl font-semibold text-gray-900">
                        {{ meeting?.title }}
                    </h2>
                    <p class="text-gray-500 text-sm mt-1">
                        Hosted by {{ meeting?.host?.name }}
                    </p>
                    <span
                        :class="statusClass"
                        class="inline-block mt-2 px-3 py-1 text-xs font-medium rounded-full"
                    >
                        {{ meeting?.status }}
                    </span>
                </div>

                <form @submit.prevent="handleJoin" class="space-y-4">
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Display Name</label
                        >
                        <input
                            v-model="displayName"
                            type="text"
                            class="input"
                            :placeholder="user?.name || 'Your name'"
                        />
                    </div>

                    <div v-if="meeting?.has_password">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Meeting Password</label
                        >
                        <input
                            v-model="password"
                            type="password"
                            class="input"
                            placeholder="Enter meeting password"
                            required
                        />
                    </div>

                    <div
                        v-if="joinError"
                        class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm"
                    >
                        {{ joinError }}
                    </div>

                    <div class="space-y-4 pt-4">
                        <h3 class="text-sm font-medium text-gray-700">
                            Join Options
                        </h3>

                        <label class="flex items-center">
                            <input
                                v-model="joinWithVideo"
                                type="checkbox"
                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                            />
                            <span class="ml-2 text-sm text-gray-700"
                                >Turn on video</span
                            >
                        </label>

                        <label class="flex items-center">
                            <input
                                v-model="joinWithAudio"
                                type="checkbox"
                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                            />
                            <span class="ml-2 text-sm text-gray-700"
                                >Turn on microphone</span
                            >
                        </label>
                    </div>

                    <button
                        type="submit"
                        :disabled="joining || meeting?.status === 'ended'"
                        class="w-full btn-primary py-3 mt-6"
                    >
                        <svg
                            v-if="joining"
                            class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        {{
                            joining
                                ? "Joining..."
                                : meeting?.status === "ended"
                                  ? "Meeting Ended"
                                  : "Join Meeting"
                        }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "../stores/auth";
import { useMeetingStore } from "../stores/meeting";

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const meetingStore = useMeetingStore();

const user = computed(() => authStore.user);

const meeting = ref(null);
const loading = ref(true);
const error = ref("");

const displayName = ref("");
const password = ref("");
const joinWithVideo = ref(true);
const joinWithAudio = ref(true);
const joining = ref(false);
const joinError = ref("");

const statusClass = computed(() => {
    const classes = {
        scheduled: "bg-yellow-100 text-yellow-800",
        active: "bg-green-100 text-green-800",
        ended: "bg-gray-100 text-gray-800",
    };
    return classes[meeting.value?.status] || classes.scheduled;
});

onMounted(async () => {
    try {
        meeting.value = await meetingStore.fetchMeetingByUuid(
            route.params.uuid,
        );
        displayName.value = user.value?.name || "";
    } catch (e) {
        error.value = e.response?.data?.message || "Meeting not found";
    } finally {
        loading.value = false;
    }
});

const handleJoin = async () => {
    joining.value = true;
    joinError.value = "";

    try {
        // Store join preferences in session storage
        sessionStorage.setItem(
            "joinPrefs",
            JSON.stringify({
                video: joinWithVideo.value,
                audio: joinWithAudio.value,
                displayName: displayName.value || user.value?.name,
            }),
        );

        await meetingStore.joinMeeting(meeting.value.id, {
            password: password.value || undefined,
            display_name: displayName.value || undefined,
        });

        router.push(`/meeting/${route.params.uuid}`);
    } catch (e) {
        joinError.value = e.response?.data?.message || "Failed to join meeting";
    } finally {
        joining.value = false;
    }
};
</script>
