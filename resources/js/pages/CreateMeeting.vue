<template>
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Create New Meeting</h1>
            <p class="text-gray-600">Set up your meeting details</p>
        </div>

        <form @submit.prevent="handleSubmit" class="card p-6 space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"
                    >Meeting Title *</label
                >
                <input
                    v-model="form.title"
                    type="text"
                    class="input"
                    placeholder="Weekly Team Standup"
                    required
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"
                    >Description</label
                >
                <textarea
                    v-model="form.description"
                    class="input"
                    rows="3"
                    placeholder="Optional meeting description..."
                ></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"
                        >Schedule for</label
                    >
                    <input
                        v-model="form.scheduled_at"
                        type="datetime-local"
                        class="input"
                    />
                    <p class="text-xs text-gray-500 mt-1">
                        Leave empty to start immediately
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"
                        >Max Participants</label
                    >
                    <input
                        v-model.number="form.max_participants"
                        type="number"
                        class="input"
                        min="2"
                        max="100"
                    />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"
                    >Meeting Password</label
                >
                <div class="flex space-x-2">
                    <input
                        v-model="form.password"
                        type="text"
                        class="input flex-1"
                        placeholder="Optional password"
                    />
                    <button
                        type="button"
                        @click="generatePassword"
                        class="btn-secondary"
                    >
                        Generate
                    </button>
                </div>
            </div>

            <div class="space-y-4">
                <h3 class="text-sm font-medium text-gray-700">
                    Meeting Options
                </h3>

                <label class="flex items-center">
                    <input
                        v-model="form.is_recording_enabled"
                        type="checkbox"
                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span class="ml-2 text-sm text-gray-700"
                        >Enable recording</span
                    >
                </label>

                <label class="flex items-center">
                    <input
                        v-model="form.is_chat_enabled"
                        type="checkbox"
                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span class="ml-2 text-sm text-gray-700"
                        >Enable in-meeting chat</span
                    >
                </label>

                <label class="flex items-center">
                    <input
                        v-model="form.is_whiteboard_enabled"
                        type="checkbox"
                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span class="ml-2 text-sm text-gray-700"
                        >Enable whiteboard</span
                    >
                </label>

                <label class="flex items-center">
                    <input
                        v-model="form.waiting_room_enabled"
                        type="checkbox"
                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span class="ml-2 text-sm text-gray-700"
                        >Enable waiting room</span
                    >
                </label>
            </div>

            <div
                v-if="error"
                class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm"
            >
                {{ error }}
            </div>

            <div class="flex justify-end space-x-3">
                <router-link to="/meetings" class="btn-secondary">
                    Cancel
                </router-link>
                <button type="submit" :disabled="loading" class="btn-primary">
                    <svg
                        v-if="loading"
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
                    {{ loading ? "Creating..." : "Create Meeting" }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, reactive } from "vue";
import { useRouter } from "vue-router";
import { useMeetingStore } from "../stores/meeting";

const router = useRouter();
const meetingStore = useMeetingStore();

const form = reactive({
    title: "",
    description: "",
    password: "",
    scheduled_at: "",
    max_participants: 50,
    is_recording_enabled: false,
    is_chat_enabled: true,
    is_whiteboard_enabled: true,
    waiting_room_enabled: false,
});

const loading = ref(false);
const error = ref("");

const generatePassword = () => {
    const chars = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789";
    let password = "";
    for (let i = 0; i < 8; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    form.password = password;
};

const handleSubmit = async () => {
    loading.value = true;
    error.value = "";

    try {
        const data = { ...form };
        if (!data.scheduled_at) delete data.scheduled_at;
        if (!data.password) delete data.password;

        const meeting = await meetingStore.createMeeting(data);

        window.$toast?.success("Meeting created successfully");

        // If no scheduled date, go directly to meeting room
        if (!form.scheduled_at) {
            router.push(`/meeting/${meeting.uuid}`);
        } else {
            router.push("/meetings");
        }
    } catch (e) {
        error.value = e.response?.data?.message || "Failed to create meeting";
    } finally {
        loading.value = false;
    }
};
</script>
