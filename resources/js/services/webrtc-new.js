/**
 * Native WebRTC Service (tanpa simple-peer)
 * Compatible dengan Vite/Browser environment
 */

import api from './api';

// ICE Server Configuration
const ICE_SERVERS = [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' },
    { urls: 'stun:stun2.l.google.com:19302' },
    {
        urls: 'turn:coturn.rrstuah.my.id:3478', // Port standar
        username: 'imsmeetuser',
        credential: 'imsmeetp@ssword',
    },
    {
        urls: 'turns:coturn.rrstuah.my.id:5349', // Port TLS/SSL
        username: 'imsmeetuser',
        credential: 'imsmeetp@ssword',
    }
    // Tambahkan TURN server untuk production
    // {
    //     urls: 'turn:your-turn-server.com:3478',
    //     username: 'username',
    //     credential: 'password',
    // },
];

export class WebRTCService {
    constructor(meetingId, userId, onRemoteStream, onRemoveStream) {
        this.meetingId = meetingId;
        this.userId = userId;
        this.onRemoteStream = onRemoteStream;
        this.onRemoveStream = onRemoveStream;
        
        this.localStream = null;
        this.peers = new Map(); // Map<odtUserId, RTCPeerConnection>
        this.pendingCandidates = new Map(); // Map<odtUserId, RTCIceCandidate[]>
        
        console.log('[WebRTC] Service initialized for user:', userId);
    }

    /**
     * Initialize local media stream
     */
    async initLocalStream(audio = true, video = true) {
        console.log('[WebRTC] Initializing local stream...');
        
        try {
            this.localStream = await navigator.mediaDevices.getUserMedia({
                audio: audio ? {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                } : false,
                video: video ? {
                    width: { ideal: 1280, max: 1920 },
                    height: { ideal: 720, max: 1080 },
                    facingMode: 'user',
                } : false,
            });
            
            console.log('[WebRTC] Local stream obtained:', this.localStream.id);
            return this.localStream;
            
        } catch (error) {
            console.error('[WebRTC] Failed to get local stream:', error);
            
            // Fallback: coba audio only
            if (video) {
                console.log('[WebRTC] Retrying with audio only...');
                return this.initLocalStream(audio, false);
            }
            
            throw error;
        }
    }

    /**
     * Create peer connection untuk user tertentu
     */
    createPeer(remoteUserId, initiator = false) {
        console.log(`[WebRTC] Creating peer for user ${remoteUserId}, initiator: ${initiator}`);
        
        // Close existing connection if any
        if (this.peers.has(remoteUserId)) {
            this.peers.get(remoteUserId).close();
            this.peers.delete(remoteUserId);
        }

        const pc = new RTCPeerConnection({
            iceServers: ICE_SERVERS,
        });

        // Add local tracks
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => {
                console.log(`[WebRTC] Adding track: ${track.kind}`);
                pc.addTrack(track, this.localStream);
            });
        }

        // Handle ICE candidates
        pc.onicecandidate = (event) => {
            if (event.candidate) {
                console.log(`[WebRTC] Sending ICE candidate to user ${remoteUserId}`);
                this.sendIceCandidate(remoteUserId, event.candidate);
            }
        };

        // Handle connection state
        pc.onconnectionstatechange = () => {
            console.log(`[WebRTC] Connection state with ${remoteUserId}: ${pc.connectionState}`);
            
            if (pc.connectionState === 'failed' || pc.connectionState === 'disconnected') {
                console.log(`[WebRTC] Connection lost with ${remoteUserId}`);
            }
            
            if (pc.connectionState === 'connected') {
                console.log(`[WebRTC] ✅ Connected to user ${remoteUserId}`);
            }
        };

        // Handle ICE connection state
        pc.oniceconnectionstatechange = () => {
            console.log(`[WebRTC] ICE state with ${remoteUserId}: ${pc.iceConnectionState}`);
        };

        // Handle remote stream
        pc.ontrack = (event) => {
            console.log(`[WebRTC] Received track from ${remoteUserId}: ${event.track.kind}`);
            
            if (event.streams && event.streams[0]) {
                this.onRemoteStream(remoteUserId, event.streams[0]);
            }
        };

        this.peers.set(remoteUserId, pc);

        // Jika initiator, buat offer
        if (initiator) {
            this.createOffer(remoteUserId);
        }

        return pc;
    }

    /**
     * Create and send offer
     */
    async createOffer(remoteUserId) {
        const pc = this.peers.get(remoteUserId);
        if (!pc) return;

        try {
            console.log(`[WebRTC] Creating offer for user ${remoteUserId}`);
            
            const offer = await pc.createOffer({
                offerToReceiveAudio: true,
                offerToReceiveVideo: true,
            });
            
            await pc.setLocalDescription(offer);
            
            console.log(`[WebRTC] Sending offer to user ${remoteUserId}`);
            await this.sendSignal(remoteUserId, 'offer', offer.sdp);
            
        } catch (error) {
            console.error(`[WebRTC] Failed to create offer:`, error);
        }
    }

    /**
     * Handle incoming signal (offer/answer)
     */
    async handleSignal(fromUserId, type, sdp) {
        console.log(`[WebRTC] Received ${type} from user ${fromUserId}`);
        
        let pc = this.peers.get(fromUserId);
        
        if (type === 'offer') {
            // Create peer if not exists (we are the receiver)
            if (!pc) {
                pc = this.createPeer(fromUserId, false);
            }
            
            try {
                await pc.setRemoteDescription(new RTCSessionDescription({
                    type: 'offer',
                    sdp: sdp,
                }));
                
                // Apply pending ICE candidates
                await this.applyPendingCandidates(fromUserId);
                
                // Create answer
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                
                console.log(`[WebRTC] Sending answer to user ${fromUserId}`);
                await this.sendSignal(fromUserId, 'answer', answer.sdp);
                
            } catch (error) {
                console.error(`[WebRTC] Failed to handle offer:`, error);
            }
            
        } else if (type === 'answer') {
            if (!pc) {
                console.error(`[WebRTC] No peer connection for answer from ${fromUserId}`);
                return;
            }
            
            try {
                await pc.setRemoteDescription(new RTCSessionDescription({
                    type: 'answer',
                    sdp: sdp,
                }));
                
                // Apply pending ICE candidates
                await this.applyPendingCandidates(fromUserId);
                
            } catch (error) {
                console.error(`[WebRTC] Failed to handle answer:`, error);
            }
        }
    }

    /**
     * Handle incoming ICE candidate
     */
    async handleIceCandidate(fromUserId, candidate) {
        console.log(`[WebRTC] Received ICE candidate from user ${fromUserId}`);
        
        const pc = this.peers.get(fromUserId);
        
        if (!pc || !pc.remoteDescription) {
            // Queue candidate for later
            console.log(`[WebRTC] Queuing ICE candidate for user ${fromUserId}`);
            if (!this.pendingCandidates.has(fromUserId)) {
                this.pendingCandidates.set(fromUserId, []);
            }
            this.pendingCandidates.get(fromUserId).push(candidate);
            return;
        }
        
        try {
            await pc.addIceCandidate(new RTCIceCandidate(candidate));
            console.log(`[WebRTC] Added ICE candidate from user ${fromUserId}`);
        } catch (error) {
            console.error(`[WebRTC] Failed to add ICE candidate:`, error);
        }
    }

    /**
     * Apply pending ICE candidates
     */
    async applyPendingCandidates(userId) {
        const candidates = this.pendingCandidates.get(userId);
        if (!candidates || candidates.length === 0) return;
        
        const pc = this.peers.get(userId);
        if (!pc) return;
        
        console.log(`[WebRTC] Applying ${candidates.length} pending candidates for user ${userId}`);
        
        for (const candidate of candidates) {
            try {
                await pc.addIceCandidate(new RTCIceCandidate(candidate));
            } catch (error) {
                console.error('[WebRTC] Failed to add pending candidate:', error);
            }
        }
        
        this.pendingCandidates.delete(userId);
    }

    /**
     * Send signal via API
     */
    async sendSignal(targetUserId, type, sdp) {
        try {
            await api.post(`/meetings/${this.meetingId}/signal`, {
                target_user_id: targetUserId,
                type: type,
                sdp: sdp,
            });
        } catch (error) {
            console.error('[WebRTC] Failed to send signal:', error);
        }
    }

    /**
     * Send ICE candidate via API
     */
    async sendIceCandidate(targetUserId, candidate) {
        try {
            await api.post(`/meetings/${this.meetingId}/ice-candidate`, {
                target_user_id: targetUserId,
                candidate: candidate.toJSON(),
            });
        } catch (error) {
            console.error('[WebRTC] Failed to send ICE candidate:', error);
        }
    }

    /**
     * Connect to a participant (initiate connection)
     */
    connectToParticipant(userId) {
        console.log(`[WebRTC] Connecting to participant ${userId}`);
        this.createPeer(userId, true);
    }

    /**
     * Disconnect from a participant
     */
    disconnectFromParticipant(userId) {
        console.log(`[WebRTC] Disconnecting from participant ${userId}`);
        
        const pc = this.peers.get(userId);
        if (pc) {
            pc.close();
            this.peers.delete(userId);
        }
        
        this.pendingCandidates.delete(userId);
        this.onRemoveStream(userId);
    }

    /**
     * Toggle audio
     */
    toggleAudio(enabled) {
        if (this.localStream) {
            this.localStream.getAudioTracks().forEach(track => {
                track.enabled = enabled;
            });
            console.log(`[WebRTC] Audio ${enabled ? 'enabled' : 'disabled'}`);
        }
    }

    /**
     * Toggle video
     */
    toggleVideo(enabled) {
        if (this.localStream) {
            this.localStream.getVideoTracks().forEach(track => {
                track.enabled = enabled;
            });
            console.log(`[WebRTC] Video ${enabled ? 'enabled' : 'disabled'}`);
        }
    }

    /**
     * Start screen sharing
     */
    async startScreenShare() {
        try {
            const screenStream = await navigator.mediaDevices.getDisplayMedia({
                video: { cursor: 'always' },
                audio: false,
            });
            
            const screenTrack = screenStream.getVideoTracks()[0];
            
            // Replace video track in all peer connections
            this.peers.forEach((pc, odtUserId) => {
                const sender = pc.getSenders().find(s => s.track?.kind === 'video');
                if (sender) {
                    sender.replaceTrack(screenTrack);
                    console.log(`[WebRTC] Replaced video track for user ${odtUserId}`);
                }
            });
            
            // Handle screen share stop
            screenTrack.onended = () => {
                this.stopScreenShare();
            };
            
            return screenStream;
            
        } catch (error) {
            console.error('[WebRTC] Failed to start screen share:', error);
            throw error;
        }
    }

    /**
     * Stop screen sharing
     */
    async stopScreenShare() {
        if (!this.localStream) return;
        
        const videoTrack = this.localStream.getVideoTracks()[0];
        if (!videoTrack) return;
        
        // Restore camera track in all peer connections
        this.peers.forEach((pc, odtUserId) => {
            const sender = pc.getSenders().find(s => s.track?.kind === 'video');
            if (sender) {
                sender.replaceTrack(videoTrack);
                console.log(`[WebRTC] Restored video track for user ${odtUserId}`);
            }
        });
    }

    /**
     * Destroy service and cleanup
     */
    destroy() {
        console.log('[WebRTC] Destroying service...');
        
        // Close all peer connections
        this.peers.forEach((pc, odtUserId) => {
            pc.close();
        });
        this.peers.clear();
        this.pendingCandidates.clear();
        
        // Stop local stream
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => {
                track.stop();
            });
            this.localStream = null;
        }
        
        console.log('[WebRTC] Service destroyed');
    }
}

export default WebRTCService;
