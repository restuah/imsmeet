import SimplePeer from "simple-peer";
import api from "./api";

export class WebRTCService {
    constructor(meetingId, userId, onStream, onRemoveStream) {
        this.meetingId = meetingId;
        this.userId = userId;
        this.peers = new Map();
        this.localStream = null;
        this.screenStream = null;
        this.onStream = onStream;
        this.onRemoveStream = onRemoveStream;
        this.iceServers = [
            { urls: "stun:stun.l.google.com:19302" },
            { urls: "stun:stun1.l.google.com:19302" },
            { urls: "stun:stun2.l.google.com:19302" },
        ];
    }

    async initLocalStream(audio = true, video = true) {
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
                          width: { ideal: 1280 },
                          height: { ideal: 720 },
                          facingMode: "user",
                      }
                    : false,
            });
            return this.localStream;
        } catch (error) {
            console.error("Failed to get local stream:", error);
            throw error;
        }
    }

    createPeer(targetUserId, initiator = true) {
        if (this.peers.has(targetUserId)) {
            this.peers.get(targetUserId).destroy();
        }

        const peer = new SimplePeer({
            initiator,
            trickle: true,
            stream: this.localStream,
            config: {
                iceServers: this.iceServers,
            },
        });

        peer.on("signal", async (data) => {
            if (data.type === "offer" || data.type === "answer") {
                await this.sendSignal(
                    targetUserId,
                    data.type,
                    JSON.stringify(data.sdp || data),
                );
            } else if (data.candidate) {
                await this.sendIceCandidate(targetUserId, data);
            }
        });

        peer.on("stream", (stream) => {
            this.onStream(targetUserId, stream);
        });

        peer.on("close", () => {
            this.peers.delete(targetUserId);
            this.onRemoveStream(targetUserId);
        });

        peer.on("error", (error) => {
            console.error(`Peer error with ${targetUserId}:`, error);
            this.peers.delete(targetUserId);
            this.onRemoveStream(targetUserId);
        });

        this.peers.set(targetUserId, peer);
        return peer;
    }

    async handleSignal(fromUserId, type, sdp) {
        let peer = this.peers.get(fromUserId);

        if (!peer) {
            peer = this.createPeer(fromUserId, false);
        }

        try {
            const signalData = JSON.parse(sdp);
            if (type === "offer") {
                peer.signal({ type: "offer", sdp: signalData });
            } else if (type === "answer") {
                peer.signal({ type: "answer", sdp: signalData });
            }
        } catch (e) {
            peer.signal({ type, sdp });
        }
    }

    handleIceCandidate(fromUserId, candidate) {
        const peer = this.peers.get(fromUserId);
        if (peer) {
            peer.signal(candidate);
        }
    }

    async sendSignal(targetUserId, type, sdp) {
        try {
            await api.post(`/meetings/${this.meetingId}/signal`, {
                target_user_id: targetUserId,
                type,
                sdp,
            });
        } catch (error) {
            console.error("Failed to send signal:", error);
        }
    }

    async sendIceCandidate(targetUserId, candidate) {
        try {
            await api.post(`/meetings/${this.meetingId}/ice-candidate`, {
                target_user_id: targetUserId,
                candidate,
            });
        } catch (error) {
            console.error("Failed to send ICE candidate:", error);
        }
    }

    toggleAudio(enabled) {
        if (this.localStream) {
            this.localStream.getAudioTracks().forEach((track) => {
                track.enabled = enabled;
            });
        }
    }

    toggleVideo(enabled) {
        if (this.localStream) {
            this.localStream.getVideoTracks().forEach((track) => {
                track.enabled = enabled;
            });
        }
    }

    async startScreenShare() {
        try {
            this.screenStream = await navigator.mediaDevices.getDisplayMedia({
                video: {
                    cursor: "always",
                },
                audio: true,
            });

            const videoTrack = this.screenStream.getVideoTracks()[0];

            // Replace video track in all peers
            this.peers.forEach((peer) => {
                const sender = peer._pc
                    ?.getSenders()
                    .find((s) => s.track?.kind === "video");
                if (sender) {
                    sender.replaceTrack(videoTrack);
                }
            });

            // Handle screen share end
            videoTrack.onended = () => {
                this.stopScreenShare();
            };

            return this.screenStream;
        } catch (error) {
            console.error("Failed to start screen share:", error);
            throw error;
        }
    }

    async stopScreenShare() {
        if (this.screenStream) {
            this.screenStream.getTracks().forEach((track) => track.stop());
            this.screenStream = null;

            // Restore camera video track
            if (this.localStream) {
                const videoTrack = this.localStream.getVideoTracks()[0];
                if (videoTrack) {
                    this.peers.forEach((peer) => {
                        const sender = peer._pc
                            ?.getSenders()
                            .find((s) => s.track?.kind === "video");
                        if (sender) {
                            sender.replaceTrack(videoTrack);
                        }
                    });
                }
            }
        }
    }

    connectToParticipant(participantUserId) {
        if (
            participantUserId !== this.userId &&
            !this.peers.has(participantUserId)
        ) {
            this.createPeer(participantUserId, true);
        }
    }

    disconnectFromParticipant(participantUserId) {
        const peer = this.peers.get(participantUserId);
        if (peer) {
            peer.destroy();
            this.peers.delete(participantUserId);
        }
    }

    destroy() {
        // Stop all tracks
        if (this.localStream) {
            this.localStream.getTracks().forEach((track) => track.stop());
            this.localStream = null;
        }

        if (this.screenStream) {
            this.screenStream.getTracks().forEach((track) => track.stop());
            this.screenStream = null;
        }

        // Destroy all peers
        this.peers.forEach((peer) => peer.destroy());
        this.peers.clear();
    }
}

export default WebRTCService;
