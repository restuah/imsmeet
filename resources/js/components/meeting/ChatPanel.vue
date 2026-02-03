<template>
    <div class="h-full flex flex-col">
        <!-- Messages -->
        <div
            ref="messagesContainer"
            class="flex-1 overflow-y-auto p-4 space-y-4"
        >
            <div
                v-if="messages.length === 0"
                class="text-center text-gray-400 text-sm py-8"
            >
                No messages yet. Start the conversation!
            </div>

            <div
                v-for="message in messages"
                :key="message.id"
                class="flex items-start space-x-3"
            >
                <div
                    class="w-8 h-8 rounded-full bg-gray-600 flex-shrink-0 flex items-center justify-center"
                >
                    <span class="text-xs font-medium text-white">{{
                        getInitials(message.user?.name)
                    }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-baseline space-x-2">
                        <span class="text-sm font-medium text-white">{{
                            message.user?.name
                        }}</span>
                        <span class="text-xs text-gray-500">{{
                            formatTime(message.created_at)
                        }}</span>
                        <span
                            v-if="message.is_private"
                            class="text-xs text-yellow-500"
                            >(Private)</span
                        >
                    </div>
                    <p class="text-sm text-gray-300 break-words">
                        {{ message.message }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Input -->
        <div class="p-4 border-t border-gray-700">
            <form @submit.prevent="handleSend" class="flex space-x-2">
                <input
                    v-model="newMessage"
                    type="text"
                    placeholder="Type a message..."
                    class="flex-1 px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    maxlength="1000"
                />
                <button
                    type="submit"
                    :disabled="!newMessage.trim()"
                    class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
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
                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                        />
                    </svg>
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, nextTick } from "vue";

const props = defineProps({
    meetingId: Number,
    messages: Array,
});

const emit = defineEmits(["send"]);

const newMessage = ref("");
const messagesContainer = ref(null);

const handleSend = () => {
    if (!newMessage.value.trim()) return;
    emit("send", newMessage.value.trim());
    newMessage.value = "";
};

const getInitials = (name) => {
    return (
        name
            ?.split(" ")
            .map((n) => n[0])
            .join("")
            .toUpperCase()
            .slice(0, 2) || "?"
    );
};

const formatTime = (date) => {
    return new Date(date).toLocaleTimeString("en-US", {
        hour: "2-digit",
        minute: "2-digit",
    });
};

// Auto-scroll to bottom when new messages arrive
watch(
    () => props.messages.length,
    async () => {
        await nextTick();
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop =
                messagesContainer.value.scrollHeight;
        }
    },
);
</script>
