<template>
    <div class="h-full flex flex-col">
        <!-- Screen Share View -->
        <div v-if="screenSharingUserId" class="flex-1 flex gap-4">
            <div class="flex-1 video-tile">
                <video
                    ref="screenShareVideo"
                    :srcObject="getStream(screenSharingUserId)"
                    autoplay
                    playsinline
                    class="w-full h-full object-contain bg-black"
                ></video>
                <div
                    class="absolute bottom-2 left-2 bg-black/60 px-2 py-1 rounded text-white text-sm"
                >
                    {{ getParticipantName(screenSharingUserId) }} - Screen Share
                </div>
            </div>
            <div class="w-64 flex flex-col gap-2 overflow-y-auto">
                <VideoTile
                    v-for="participant in allParticipants"
                    :key="participant.user_id"
                    :participant="participant"
                    :stream="getStream(participant.user_id)"
                    :is-local="participant.user_id === currentUserId"
                    :local-stream="localStream"
                    class="h-36"
                />
            </div>
        </div>

        <!-- Normal Grid View -->
        <div v-else :class="gridClass" class="grid gap-4 h-full auto-rows-fr">
            <VideoTile
                v-for="participant in allParticipants"
                :key="participant.user_id"
                :participant="participant"
                :stream="getStream(participant.user_id)"
                :is-local="participant.user_id === currentUserId"
                :local-stream="localStream"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch, nextTick } from "vue";
import VideoTile from "./VideoTile.vue";

const props = defineProps({
    localStream: Object,
    remoteStreams: Map,
    participants: Array,
    currentUserId: Number,
    screenSharingUserId: Number,
});

const screenShareVideo = ref(null);

const allParticipants = computed(() => {
    const currentUser = props.participants.find(
        (p) => p.user_id === props.currentUserId,
    );
    const others = props.participants.filter(
        (p) => p.user_id !== props.currentUserId,
    );

    if (currentUser) {
        return [currentUser, ...others];
    }
    return others;
});

const gridClass = computed(() => {
    const count = allParticipants.value.length;
    if (count <= 1) return "video-grid-1";
    if (count <= 2) return "video-grid-2";
    if (count <= 4) return "video-grid-4";
    return "video-grid-many";
});

const getStream = (userId) => {
    if (userId === props.currentUserId) {
        return props.localStream;
    }
    return props.remoteStreams?.get(userId);
};

const getParticipantName = (userId) => {
    const participant = props.participants.find((p) => p.user_id === userId);
    return participant?.display_name || "Unknown";
};

watch(
    () => props.screenSharingUserId,
    async (newVal) => {
        if (newVal && screenShareVideo.value) {
            await nextTick();
            const stream = getStream(newVal);
            if (stream && screenShareVideo.value) {
                screenShareVideo.value.srcObject = stream;
            }
        }
    },
);
</script>
