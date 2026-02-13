<template>
  <div class="h-full flex flex-col">
    <!-- Screen Share View - Responsive -->
    <div
      v-if="screenSharingUserId"
      class="flex-1 flex flex-col lg:flex-row gap-2 sm:gap-4"
    >
      <!-- Main Screen Share - Takes 80% on desktop -->
      <div class="flex-1 lg:flex-[4] video-tile min-h-0">
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

      <!-- Participant Strip - Horizontal on mobile, Vertical on desktop -->
      <div
        class="flex lg:flex-col gap-2 overflow-x-auto lg:overflow-x-visible lg:overflow-y-auto h-24 sm:h-32 lg:h-full lg:w-48 xl:w-64 flex-shrink-0"
      >
        <VideoTile
          v-for="participant in allParticipants"
          :key="participant.user_id"
          :participant="participant"
          :stream="getStream(participant.user_id)"
          :is-local="participant.user_id === currentUserId"
          :local-stream="localStream"
          :is-active-speaker="activeSpeakerId === participant.user_id"
          class="w-32 sm:w-40 lg:w-full h-full lg:h-28 xl:h-36 flex-shrink-0"
        />
      </div>
    </div>

    <!-- Normal Grid View - Responsive -->
    <div v-else :class="gridClass" class="h-full gap-2 sm:gap-4">
      <VideoTile
        v-for="participant in allParticipants"
        :key="participant.user_id"
        :participant="participant"
        :stream="getStream(participant.user_id)"
        :is-local="participant.user_id === currentUserId"
        :local-stream="localStream"
        :is-active-speaker="activeSpeakerId === participant.user_id"
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
  activeSpeakerId: Number, // NEW: Active speaker prop
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

// RESPONSIVE GRID CLASSES
const gridClass = computed(() => {
  const count = allParticipants.value.length;

  if (count <= 1) {
    // Single participant - centered
    return "grid grid-cols-1 place-items-center";
  }
  if (count <= 2) {
    // 2 participants - 1 col on mobile, 2 cols on tablet+
    return "grid grid-cols-1 sm:grid-cols-2 auto-rows-fr";
  }
  if (count <= 4) {
    // 2-4 participants - 1 col mobile, 2 cols tablet+
    return "grid grid-cols-1 sm:grid-cols-2 auto-rows-fr";
  }
  if (count <= 6) {
    // 5-6 participants - 2 cols mobile, 3 cols tablet+
    return "grid grid-cols-2 md:grid-cols-3 auto-rows-fr";
  }
  if (count <= 9) {
    // 7-9 participants - 2 cols mobile, 3 cols tablet, 3 cols desktop
    return "grid grid-cols-2 md:grid-cols-3 auto-rows-fr";
  }
  // 10+ participants - many grid
  return "grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 auto-rows-fr";
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

<style scoped>
/* Base video tile styling */
.video-tile {
  @apply relative rounded-lg overflow-hidden bg-gray-800;
}
</style>
