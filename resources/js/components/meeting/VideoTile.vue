<template>
    <div class="video-tile relative group">
        <!-- Video Element -->
        <video
            v-if="hasVideo"
            ref="videoElement"
            :srcObject="displayStream"
            autoplay
            playsinline
            :muted="isLocal"
            class="w-full h-full object-cover"
        ></video>

        <!-- No Video Placeholder -->
        <div
            v-else
            class="w-full h-full flex items-center justify-center bg-gray-700"
        >
            <div
                class="w-20 h-20 rounded-full bg-gray-600 flex items-center justify-center"
            >
                <span class="text-2xl font-bold text-white">{{
                    initials
                }}</span>
            </div>
        </div>

        <!-- Participant Info Overlay -->
        <div
            class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-3"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span
                        class="text-white text-sm font-medium truncate max-w-[150px]"
                    >
                        {{ participant.display_name }}
                        <span v-if="isLocal" class="text-gray-300">(You)</span>
                    </span>
                    <span
                        v-if="participant.role === 'host'"
                        class="px-1.5 py-0.5 bg-yellow-500 text-xs text-black rounded"
                    >
                        Host
                    </span>
                    <span
                        v-else-if="participant.role === 'co-host'"
                        class="px-1.5 py-0.5 bg-blue-500 text-xs text-white rounded"
                    >
                        Co-host
                    </span>
                </div>
                <div class="flex items-center space-x-1">
                    <!-- Hand Raised -->
                    <div
                        v-if="participant.is_hand_raised"
                        class="p-1 bg-yellow-500 rounded"
                        title="Hand raised"
                    >
                        <svg
                            class="w-4 h-4 text-white"
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
                    </div>
                    <!-- Muted -->
                    <div
                        v-if="participant.is_muted"
                        class="p-1 bg-red-500 rounded"
                        title="Muted"
                    >
                        <svg
                            class="w-4 h-4 text-white"
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
                    </div>
                    <!-- Video Off -->
                    <div
                        v-if="participant.is_video_off"
                        class="p-1 bg-gray-500 rounded"
                        title="Camera off"
                    >
                        <svg
                            class="w-4 h-4 text-white"
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
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 3l18 18"
                            />
                        </svg>
                    </div>
                    <!-- Screen Sharing -->
                    <div
                        v-if="participant.is_screen_sharing"
                        class="p-1 bg-green-500 rounded"
                        title="Sharing screen"
                    >
                        <svg
                            class="w-4 h-4 text-white"
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
                    </div>
                </div>
            </div>
        </div>

        <!-- Audio Level Indicator -->
        <div
            v-if="!participant.is_muted && audioLevel > 0"
            class="absolute top-2 right-2 w-3 h-3 bg-green-500 rounded-full animate-pulse"
            :style="{ opacity: Math.min(audioLevel / 100, 1) }"
        ></div>
    </div>
</template>

<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount } from "vue";

const props = defineProps({
    participant: Object,
    stream: Object,
    isLocal: Boolean,
    localStream: Object,
});

const videoElement = ref(null);
const audioLevel = ref(0);
let audioContext = null;
let analyser = null;
let animationFrame = null;

const displayStream = computed(() => {
    return props.isLocal ? props.localStream : props.stream;
});

const hasVideo = computed(() => {
    if (props.participant.is_video_off) return false;
    const stream = displayStream.value;
    if (!stream) return false;
    const videoTracks = stream.getVideoTracks();
    return videoTracks.length > 0 && videoTracks[0].enabled;
});

const initials = computed(() => {
    const name = props.participant?.display_name || "";
    return (
        name
            .split(" ")
            .map((n) => n[0])
            .join("")
            .toUpperCase()
            .slice(0, 2) || "?"
    );
});

const setupAudioAnalyser = () => {
    const stream = displayStream.value;
    if (!stream || props.participant.is_muted) return;

    try {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
        analyser = audioContext.createAnalyser();
        const source = audioContext.createMediaStreamSource(stream);
        source.connect(analyser);
        analyser.fftSize = 256;

        const dataArray = new Uint8Array(analyser.frequencyBinCount);

        const checkAudioLevel = () => {
            if (!analyser) return;
            analyser.getByteFrequencyData(dataArray);
            const average =
                dataArray.reduce((a, b) => a + b) / dataArray.length;
            audioLevel.value = average;
            animationFrame = requestAnimationFrame(checkAudioLevel);
        };

        checkAudioLevel();
    } catch (e) {
        console.error("Failed to setup audio analyser:", e);
    }
};

const cleanupAudioAnalyser = () => {
    if (animationFrame) cancelAnimationFrame(animationFrame);
    if (audioContext) audioContext.close().catch(() => {});
    audioContext = null;
    analyser = null;
};

watch(
    displayStream,
    (newStream) => {
        if (videoElement.value && newStream) {
            videoElement.value.srcObject = newStream;
        }
        cleanupAudioAnalyser();
        if (newStream && !props.participant.is_muted) {
            setupAudioAnalyser();
        }
    },
    { immediate: true },
);

watch(
    () => props.participant.is_muted,
    (muted) => {
        if (muted) {
            cleanupAudioAnalyser();
            audioLevel.value = 0;
        } else if (displayStream.value) {
            setupAudioAnalyser();
        }
    },
);

onMounted(() => {
    if (displayStream.value && !props.participant.is_muted) {
        setupAudioAnalyser();
    }
});

onBeforeUnmount(() => {
    cleanupAudioAnalyser();
});
</script>
