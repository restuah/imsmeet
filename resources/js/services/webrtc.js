import api from "./api";

/**
 * WebRTC Service with:
 * - Perfect Negotiation Pattern
 * - Adaptive Bitrate Control
 * - Proper Track Management (enabled vs stop)
 * - Lazy Video Loading
 * - Complete Cleanup
 */

// Default ICE Servers (akan di-override dari backend)
let ICE_SERVERS = [
  { urls: "stun:stun.l.google.com:19302" },
  { urls: "stun:stun.l.google.com:5349" },
  { urls: "stun:stun1.l.google.com:3478" },
  { urls: "stun:stun1.l.google.com:5349" },
  { urls: "stun:stun2.l.google.com:19302" },
  { urls: "stun:stun2.l.google.com:5349" },
  { urls: "stun:stun3.l.google.com:3478" },
  { urls: "stun:stun3.l.google.com:5349" },
  { urls: "stun:stun4.l.google.com:19302" },
  { urls: "stun:stun4.l.google.com:5349" },
];

// Bitrate configurations for adaptive streaming
const BITRATE_CONFIGS = {
  high: { maxBitrate: 2500000, maxFramerate: 30 }, // 2.5 Mbps - Full HD
  medium: { maxBitrate: 1000000, maxFramerate: 24 }, // 1 Mbps - HD
  low: { maxBitrate: 500000, maxFramerate: 15 }, // 500 Kbps - SD
  minimal: { maxBitrate: 150000, maxFramerate: 10 }, // 150 Kbps - Thumbnail
};

function encodeSDP(sdp) {
  return btoa(unescape(encodeURIComponent(sdp)));
}

function decodeSDP(encoded) {
  return decodeURIComponent(escape(atob(encoded)));
}

export class WebRTCService {
  constructor(
    meetingId,
    userId,
    onRemoteStream,
    onRemoveStream,
    onBitrateChange = null,
  ) {
    this.meetingId = meetingId;
    this.userId = userId;
    this.onRemoteStream = onRemoteStream;
    this.onRemoveStream = onRemoveStream;
    this.onBitrateChange = onBitrateChange;

    this.localStream = null;
    this.screenStream = null;
    this.peers = new Map(); // Map<userId, PeerState>
    this.pendingCandidates = new Map();
    this.bitrateMonitors = new Map(); // Map<userId, IntervalId>

    // Lazy loading state
    this.visibleUsers = new Set(); // Users currently visible in grid
    this.priorityUsers = new Set(); // Active speaker, hand raised, screen sharing

    // Track if service is destroyed
    this.destroyed = false;

    // console.log("[WebRTC] Service initialized for user:", userId);
  }

  /**
   * Load ICE servers from backend (includes secure Coturn credentials)
   */
  async loadIceServers() {
    try {
      const response = await api.get(`/meetings/${this.meetingId}/ice-servers`);
      if (response.data.iceServers && response.data.iceServers.length > 0) {
        ICE_SERVERS = response.data.iceServers;
        // console.log("[WebRTC] ICE servers loaded from backend");
      }
    } catch (error) {
      console.warn(
        "[WebRTC] Failed to load ICE servers from backend, using defaults:",
        error,
      );
    }
  }

  /**
   * Initialize local media stream with COMPLETE cleanup of previous stream
   */
  async initLocalStream(audio = true, video = true) {
    // console.log(
    //   "[WebRTC] Initializing local stream - audio:",
    //   audio,
    //   "video:",
    //   video,
    // );

    // CRITICAL: Stop any existing stream FIRST to release hardware
    if (this.localStream) {
      // console.log(
      //   "[WebRTC] Stopping existing local stream before acquiring new one",
      // );
      this.localStream.getTracks().forEach((track) => {
        track.stop();
        // console.log(`[WebRTC] Stopped existing track: ${track.kind}`);
      });
      this.localStream = null;

      // Small delay to ensure hardware is released
      await new Promise((resolve) => setTimeout(resolve, 100));
    }

    try {
      this.localStream = await navigator.mediaDevices.getUserMedia({
        audio: audio
          ? {
              echoCancellation: true,
              noiseSuppression: true,
              autoGainControl: true,
            }
          : false,
        video: video
          ? {
              width: { ideal: 1280, max: 1920 },
              height: { ideal: 720, max: 1080 },
              frameRate: { ideal: 30, max: 30 },
              facingMode: "user",
            }
          : false,
      });

      // console.log("[WebRTC] Local stream obtained:", this.localStream.id);
      // console.log(
      //   "[WebRTC] Audio tracks:",
      //   this.localStream.getAudioTracks().length,
      // );
      // console.log(
      //   "[WebRTC] Video tracks:",
      //   this.localStream.getVideoTracks().length,
      // );

      return this.localStream;
    } catch (error) {
      console.error("[WebRTC] Failed to get local stream:", error);

      // Handle specific errors
      if (error.name === "NotReadableError" || error.name === "NotFoundError") {
        // console.log("[WebRTC] Hardware error, attempting recovery...");

        // Wait longer and retry once
        await new Promise((resolve) => setTimeout(resolve, 500));

        try {
          // Try audio only if video failed
          if (video) {
            // console.log("[WebRTC] Retrying with audio only...");
            return await this.initLocalStream(audio, false);
          }
        } catch (retryError) {
          console.error("[WebRTC] Retry also failed:", retryError);
          throw retryError;
        }
      }

      throw error;
    }
  }

  /**
   * Determine politeness for Perfect Negotiation
   */
  isPolite(remoteUserId) {
    return this.userId < remoteUserId;
  }

  /**
   * Create peer connection with complete state management
   */
  createPeer(remoteUserId) {
    if (this.destroyed) {
      console.warn("[WebRTC] Service destroyed, cannot create peer");
      return null;
    }

    // console.log(`[WebRTC] Creating peer for user ${remoteUserId}`);

    // Close existing connection completely
    if (this.peers.has(remoteUserId)) {
      this.closePeer(remoteUserId);
    }

    const pc = new RTCPeerConnection({
      iceServers: ICE_SERVERS,
      // Enable features for better connectivity
      iceCandidatePoolSize: 10,
    });

    const peerState = {
      pc: pc,
      makingOffer: false,
      ignoreOffer: false,
      isVisible: this.visibleUsers.has(remoteUserId),
      isPriority: this.priorityUsers.has(remoteUserId),
      currentBitrate: "high",
    };

    this.peers.set(remoteUserId, peerState);

    // Add local tracks
    if (this.localStream) {
      this.localStream.getTracks().forEach((track) => {
        // console.log(
        //   `[WebRTC] Adding ${track.kind} track to peer ${remoteUserId}`,
        // );
        pc.addTrack(track, this.localStream);
      });
    }

    // Perfect Negotiation: Handle negotiation needed
    pc.onnegotiationneeded = async () => {
      if (this.destroyed) return;

      // console.log(`[WebRTC] Negotiation needed with ${remoteUserId}`);

      try {
        peerState.makingOffer = true;
        await pc.setLocalDescription();

        const encodedSdp = encodeSDP(pc.localDescription.sdp);
        await this.sendSignal(
          remoteUserId,
          pc.localDescription.type,
          encodedSdp,
        );
      } catch (error) {
        console.error("[WebRTC] Negotiation error:", error);
      } finally {
        peerState.makingOffer = false;
      }
    };

    // Handle ICE candidates
    pc.onicecandidate = (event) => {
      if (event.candidate && !this.destroyed) {
        this.sendIceCandidate(remoteUserId, event.candidate);
      }
    };

    // Handle connection state changes
    pc.onconnectionstatechange = () => {
      const state = pc.connectionState;
      // console.log(`[WebRTC] Connection state with ${remoteUserId}: ${state}`);

      if (state === "connected") {
        console.log(`[WebRTC] ✅ Connected to user ${remoteUserId}`);
        // Start bitrate monitoring
        this.startBitrateMonitor(remoteUserId);
        // Apply initial bitrate based on visibility
        this.updatePeerBitrate(remoteUserId);
      }

      if (state === "failed") {
        console.log(
          `[WebRTC] Connection failed with ${remoteUserId}, restarting ICE...`,
        );
        pc.restartIce();
      }

      if (state === "disconnected" || state === "closed") {
        this.stopBitrateMonitor(remoteUserId);
      }
    };

    // Handle ICE connection state
    pc.oniceconnectionstatechange = () => {
      // console.log(
      //   `[WebRTC] ICE state with ${remoteUserId}: ${pc.iceConnectionState}`,
      // );
    };

    // Handle remote tracks
    pc.ontrack = (event) => {
      // console.log(
      //   `[WebRTC] Received ${event.track.kind} track from ${remoteUserId}`,
      // );

      if (event.streams && event.streams[0]) {
        this.onRemoteStream(remoteUserId, event.streams[0]);
      }
    };

    return peerState;
  }

  /**
   * Close a specific peer connection completely
   */
  closePeer(userId) {
    // console.log(`[WebRTC] Closing peer connection for user ${userId}`);

    const peerState = this.peers.get(userId);
    if (peerState) {
      // Stop bitrate monitoring
      this.stopBitrateMonitor(userId);

      // Close the connection
      try {
        peerState.pc.close();
      } catch (e) {
        console.warn(`[WebRTC] Error closing peer ${userId}:`, e);
      }

      this.peers.delete(userId);
    }

    this.pendingCandidates.delete(userId);
  }

  /**
   * Handle incoming signal with Perfect Negotiation pattern
   * This prevents SDP m-lines ordering issues
   */
  async handleSignal(fromUserId, type, encodedSdp) {
    if (this.destroyed) return;

    // console.log(`[WebRTC] Received ${type} from user ${fromUserId}`);

    let sdp;
    try {
      sdp = decodeSDP(encodedSdp);
    } catch (e) {
      console.error("[WebRTC] Failed to decode SDP:", e);
      return;
    }

    let peerState = this.peers.get(fromUserId);
    if (!peerState) {
      peerState = this.createPeer(fromUserId);
      if (!peerState) return;
    }

    const pc = peerState.pc;
    const polite = this.isPolite(fromUserId);

    try {
      // Perfect Negotiation: Check for offer collision
      const offerCollision =
        type === "offer" &&
        (peerState.makingOffer || pc.signalingState !== "stable");

      peerState.ignoreOffer = !polite && offerCollision;

      if (peerState.ignoreOffer) {
        // console.log(
        //   `[WebRTC] Ignoring colliding offer from ${fromUserId} (we are impolite)`,
        // );
        return;
      }

      // Handle rollback for polite peer
      if (offerCollision && polite) {
        // console.log(`[WebRTC] Rolling back our offer (we are polite)`);
        await pc.setLocalDescription({ type: "rollback" });
      }

      // Set remote description
      await pc.setRemoteDescription(
        new RTCSessionDescription({
          type: type,
          sdp: sdp,
        }),
      );

      // Apply pending ICE candidates
      await this.applyPendingCandidates(fromUserId);

      // If offer, create and send answer
      if (type === "offer") {
        await pc.setLocalDescription();
        const encodedAnswer = encodeSDP(pc.localDescription.sdp);
        await this.sendSignal(
          fromUserId,
          pc.localDescription.type,
          encodedAnswer,
        );
      }
    } catch (error) {
      console.error(`[WebRTC] Failed to handle ${type}:`, error);

      // Recovery: If m-lines error, recreate peer
      if (error.message && error.message.includes("m-lines")) {
        // console.log("[WebRTC] Detected m-lines error, recreating peer...");
        this.closePeer(fromUserId);

        // Wait a bit then reconnect
        setTimeout(() => {
          if (!this.destroyed) {
            this.connectToParticipant(fromUserId);
          }
        }, 1000);
      }
    }
  }

  /**
   * Handle ICE candidates
   */
  async handleIceCandidate(fromUserId, candidate) {
    if (this.destroyed) return;

    const peerState = this.peers.get(fromUserId);

    if (!peerState || !peerState.pc.remoteDescription) {
      // Queue for later
      if (!this.pendingCandidates.has(fromUserId)) {
        this.pendingCandidates.set(fromUserId, []);
      }
      this.pendingCandidates.get(fromUserId).push(candidate);
      return;
    }

    try {
      await peerState.pc.addIceCandidate(new RTCIceCandidate(candidate));
    } catch (error) {
      if (!peerState.ignoreOffer) {
        console.error("[WebRTC] Failed to add ICE candidate:", error);
      }
    }
  }

  /**
   * Apply pending ICE candidates
   */
  async applyPendingCandidates(userId) {
    const candidates = this.pendingCandidates.get(userId);
    if (!candidates || candidates.length === 0) return;

    const peerState = this.peers.get(userId);
    if (!peerState) return;

    // console.log(
    //   `[WebRTC] Applying ${candidates.length} pending candidates for user ${userId}`,
    // );

    for (const candidate of candidates) {
      try {
        await peerState.pc.addIceCandidate(new RTCIceCandidate(candidate));
      } catch (error) {
        console.warn("[WebRTC] Failed to add pending candidate:", error);
      }
    }

    this.pendingCandidates.delete(userId);
  }

  /**
   * Send signaling data
   */
  async sendSignal(targetUserId, type, sdp) {
    if (this.destroyed) return;

    try {
      await api.post(`/meetings/${this.meetingId}/signal`, {
        target_user_id: targetUserId,
        type: type,
        sdp: sdp,
      });
    } catch (error) {
      console.error("[WebRTC] Failed to send signal:", error);
    }
  }

  /**
   * Send ICE candidate
   */
  async sendIceCandidate(targetUserId, candidate) {
    if (this.destroyed) return;

    try {
      await api.post(`/meetings/${this.meetingId}/ice-candidate`, {
        target_user_id: targetUserId,
        candidate: candidate.toJSON(),
      });
    } catch (error) {
      console.error("[WebRTC] Failed to send ICE candidate:", error);
    }
  }

  /**
   * Connect to participant
   */
  connectToParticipant(userId) {
    if (this.destroyed) return;

    // console.log(`[WebRTC] Connecting to participant ${userId}`);

    if (!this.peers.has(userId)) {
      this.createPeer(userId);
    }
  }

  /**
   * Disconnect from participant - COMPLETE cleanup
   */
  disconnectFromParticipant(userId) {
    console.log(`[WebRTC] Disconnecting from participant ${userId}`);

    // Close peer connection
    this.closePeer(userId);

    // Remove from visibility sets
    this.visibleUsers.delete(userId);
    this.priorityUsers.delete(userId);

    // Notify removal
    this.onRemoveStream(userId);
  }

  // ==========================================
  // MEDIA CONTROL - Using track.enabled ONLY
  // NEVER use track.stop() for mute/unmute
  // ==========================================

  /**
   * Toggle audio - CRITICAL: Use enabled, NOT stop
   */
  toggleAudio(enabled) {
    if (this.localStream) {
      this.localStream.getAudioTracks().forEach((track) => {
        track.enabled = enabled;
      });
      console.log(`[WebRTC] Audio ${enabled ? "enabled" : "disabled"}`);
    }
  }

  /**
   * Toggle video - CRITICAL: Use enabled, NOT stop
   */
  toggleVideo(enabled) {
    if (this.localStream) {
      this.localStream.getVideoTracks().forEach((track) => {
        track.enabled = enabled;
      });
      console.log(`[WebRTC] Video ${enabled ? "enabled" : "disabled"}`);
    }
  }

  // ==========================================
  // SCREEN SHARING
  // ==========================================

  async startScreenShare() {
    try {
      this.screenStream = await navigator.mediaDevices.getDisplayMedia({
        video: {
          cursor: "always",
          displaySurface: "monitor",
        },
        audio: false,
      });

      const screenTrack = this.screenStream.getVideoTracks()[0];

      // Replace video track in all peers
      this.peers.forEach((peerState, odtUserId) => {
        const sender = peerState.pc
          .getSenders()
          .find((s) => s.track?.kind === "video");
        if (sender) {
          sender.replaceTrack(screenTrack);
        }
      });

      screenTrack.onended = () => {
        this.stopScreenShare();
      };

      return this.screenStream;
    } catch (error) {
      console.error("[WebRTC] Failed to start screen share:", error);
      throw error;
    }
  }

  async stopScreenShare() {
    // Stop screen stream tracks
    if (this.screenStream) {
      this.screenStream.getTracks().forEach((track) => track.stop());
      this.screenStream = null;
    }

    // Restore camera track
    if (this.localStream) {
      const videoTrack = this.localStream.getVideoTracks()[0];
      if (videoTrack) {
        this.peers.forEach((peerState) => {
          const sender = peerState.pc
            .getSenders()
            .find((s) => s.track?.kind === "video");
          if (sender) {
            sender.replaceTrack(videoTrack);
          }
        });
      }
    }
  }

  // ==========================================
  // ADAPTIVE BITRATE & BANDWIDTH OPTIMIZATION
  // ==========================================

  /**
   * Update which users are visible in the grid
   */
  setVisibleUsers(userIds) {
    this.visibleUsers = new Set(userIds);

    // Update all peer bitrates
    this.peers.forEach((peerState, odtUserId) => {
      this.updatePeerBitrate(odtUserId);
    });
  }

  /**
   * Set priority users (active speaker, hand raised, screen sharing)
   */
  setPriorityUsers(userIds) {
    this.priorityUsers = new Set(userIds);

    // Update all peer bitrates
    this.peers.forEach((peerState, odtUserId) => {
      this.updatePeerBitrate(odtUserId);
    });
  }

  /**
   * Update bitrate for a specific peer based on visibility
   */
  async updatePeerBitrate(userId) {
    const peerState = this.peers.get(userId);
    if (!peerState) return;

    const isVisible = this.visibleUsers.has(userId);
    const isPriority = this.priorityUsers.has(userId);

    // Determine target bitrate
    let targetBitrate;
    if (isPriority) {
      targetBitrate = "high";
    } else if (isVisible) {
      targetBitrate = "medium";
    } else {
      targetBitrate = "minimal"; // Almost pause for hidden users
    }

    if (peerState.currentBitrate === targetBitrate) return;

    console.log(
      `[WebRTC] Setting bitrate for user ${userId}: ${targetBitrate}`,
    );
    peerState.currentBitrate = targetBitrate;

    const config = BITRATE_CONFIGS[targetBitrate];

    // Apply to video sender
    const sender = peerState.pc
      .getSenders()
      .find((s) => s.track?.kind === "video");
    if (sender) {
      try {
        const params = sender.getParameters();
        if (!params.encodings || params.encodings.length === 0) {
          params.encodings = [{}];
        }

        params.encodings[0].maxBitrate = config.maxBitrate;
        params.encodings[0].maxFramerate = config.maxFramerate;

        // For minimal, also scale down resolution
        if (targetBitrate === "minimal") {
          params.encodings[0].scaleResolutionDownBy = 4;
        } else if (targetBitrate === "low") {
          params.encodings[0].scaleResolutionDownBy = 2;
        } else {
          params.encodings[0].scaleResolutionDownBy = 1;
        }

        await sender.setParameters(params);

        if (this.onBitrateChange) {
          this.onBitrateChange(userId, targetBitrate, config);
        }
      } catch (error) {
        console.warn(`[WebRTC] Failed to set bitrate for ${userId}:`, error);
      }
    }
  }

  /**
   * Start monitoring bitrate/bandwidth for a peer
   */
  startBitrateMonitor(userId) {
    if (this.bitrateMonitors.has(userId)) return;

    const peerState = this.peers.get(userId);
    if (!peerState) return;

    const intervalId = setInterval(async () => {
      try {
        const stats = await peerState.pc.getStats();
        let totalBytesSent = 0;
        let totalBytesReceived = 0;

        stats.forEach((report) => {
          if (report.type === "outbound-rtp" && report.kind === "video") {
            totalBytesSent = report.bytesSent || 0;
          }
          if (report.type === "inbound-rtp" && report.kind === "video") {
            totalBytesReceived = report.bytesReceived || 0;
          }
        });

        // Could implement adaptive quality based on bandwidth here
        // For now, just logging
      } catch (e) {
        // Ignore stats errors
      }
    }, 5000);

    this.bitrateMonitors.set(userId, intervalId);
  }

  /**
   * Stop bitrate monitoring for a peer
   */
  stopBitrateMonitor(userId) {
    const intervalId = this.bitrateMonitors.get(userId);
    if (intervalId) {
      clearInterval(intervalId);
      this.bitrateMonitors.delete(userId);
    }
  }

  // ==========================================
  // LAZY VIDEO LOADING - Pause/Resume tracks
  // ==========================================

  /**
   * Pause receiving video from a user (they're not visible)
   */
  pauseUserVideo(userId) {
    const peerState = this.peers.get(userId);
    if (!peerState) return;

    // Set minimal bitrate
    this.updatePeerBitrate(userId);

    // console.log(`[WebRTC] Paused video for hidden user ${userId}`);
  }

  /**
   * Resume receiving video from a user (they're now visible)
   */
  resumeUserVideo(userId) {
    this.visibleUsers.add(userId);
    this.updatePeerBitrate(userId);

    // console.log(`[WebRTC] Resumed video for visible user ${userId}`);
  }

  // ==========================================
  // COMPLETE CLEANUP - CRITICAL for hardware release
  // ==========================================

  /**
   * Destroy the service completely
   * MUST be called on unmount to release hardware
   */
  destroy() {
    // console.log("[WebRTC] Destroying service completely...");

    this.destroyed = true;

    // Stop all bitrate monitors
    this.bitrateMonitors.forEach((intervalId) => {
      clearInterval(intervalId);
    });
    this.bitrateMonitors.clear();

    // Close all peer connections
    this.peers.forEach((peerState, odtUserId) => {
      try {
        peerState.pc.close();
      } catch (e) {
        console.warn(`[WebRTC] Error closing peer ${odtUserId}:`, e);
      }
    });
    this.peers.clear();
    this.pendingCandidates.clear();

    // CRITICAL: Stop ALL local stream tracks to release hardware
    if (this.localStream) {
      this.localStream.getTracks().forEach((track) => {
        track.stop();
        // console.log(`[WebRTC] Stopped local ${track.kind} track`);
      });
      this.localStream = null;
    }

    // Stop screen share stream if active
    if (this.screenStream) {
      this.screenStream.getTracks().forEach((track) => {
        track.stop();
      });
      this.screenStream = null;
    }

    // Clear visibility sets
    this.visibleUsers.clear();
    this.priorityUsers.clear();

    // console.log("[WebRTC] Service destroyed, hardware released");
  }
}

export default WebRTCService;
