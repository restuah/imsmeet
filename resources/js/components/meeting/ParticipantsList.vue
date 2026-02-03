<template>
    <div class="h-full flex flex-col">
        <div class="p-4 border-b border-gray-700">
            <input
                v-model="search"
                type="text"
                placeholder="Search participants..."
                class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
        </div>

        <div class="flex-1 overflow-y-auto">
            <div
                v-for="participant in filteredParticipants"
                :key="participant.id"
                class="px-4 py-3 hover:bg-gray-700/50 flex items-center justify-between group"
            >
                <div class="flex items-center space-x-3 min-w-0">
                    <div class="relative flex-shrink-0">
                        <div
                            class="w-10 h-10 rounded-full bg-gray-600 flex items-center justify-center"
                        >
                            <span class="text-sm font-medium text-white">{{
                                getInitials(participant.display_name)
                            }}</span>
                        </div>
                        <span
                            v-if="!participant.is_muted"
                            class="absolute -bottom-1 -right-1 w-3 h-3 bg-green-500 rounded-full border-2 border-gray-800"
                        ></span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-white text-sm font-medium truncate">
                            {{ participant.display_name }}
                            <span
                                v-if="participant.user_id === currentUserId"
                                class="text-gray-400"
                                >(You)</span
                            >
                        </p>
                        <p class="text-gray-400 text-xs">
                            <span
                                v-if="participant.role === 'host'"
                                class="text-yellow-400"
                                >Host</span
                            >
                            <span
                                v-else-if="participant.role === 'co-host'"
                                class="text-blue-400"
                                >Co-host</span
                            >
                            <span v-else>Participant</span>
                        </p>
                    </div>
                </div>

                <!-- Status Icons -->
                <div class="flex items-center space-x-2">
                    <span
                        v-if="participant.is_hand_raised"
                        class="text-yellow-400"
                        title="Hand raised"
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
                                d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11"
                            />
                        </svg>
                    </span>
                    <span
                        v-if="participant.is_muted"
                        class="text-red-400"
                        title="Muted"
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
                                d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"
                            />
                        </svg>
                    </span>
                    <span
                        v-if="participant.is_screen_sharing"
                        class="text-green-400"
                        title="Sharing screen"
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
                                d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                            />
                        </svg>
                    </span>

                    <!-- Host Actions -->
                    <div
                        v-if="
                            isHost &&
                            participant.user_id !== currentUserId &&
                            participant.role !== 'host'
                        "
                        class="flex items-center space-x-1 opacity-0 group-hover:opacity-100 transition-opacity"
                    >
                        <button
                            @click="$emit('mute', participant)"
                            class="p-1 text-gray-400 hover:text-white hover:bg-gray-600 rounded"
                            :title="participant.is_muted ? 'Unmute' : 'Mute'"
                        >
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
                                    d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"
                                />
                            </svg>
                        </button>
                        <button
                            @click="$emit('promote', participant)"
                            class="p-1 text-gray-400 hover:text-white hover:bg-gray-600 rounded"
                            :title="
                                participant.role === 'co-host'
                                    ? 'Demote'
                                    : 'Make co-host'
                            "
                        >
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
                                    d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"
                                />
                            </svg>
                        </button>
                        <button
                            @click="$emit('kick', participant)"
                            class="p-1 text-red-400 hover:text-red-300 hover:bg-red-900/50 rounded"
                            title="Remove from meeting"
                        >
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
                                    d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="filteredParticipants.length === 0"
                class="p-4 text-center text-gray-400 text-sm"
            >
                No participants found
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from "vue";

const props = defineProps({
    participants: Array,
    currentUserId: Number,
    isHost: Boolean,
});

defineEmits(["mute", "kick", "promote"]);

const search = ref("");

const filteredParticipants = computed(() => {
    if (!search.value) return props.participants;
    const query = search.value.toLowerCase();
    return props.participants.filter((p) =>
        p.display_name.toLowerCase().includes(query),
    );
});

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
</script>
