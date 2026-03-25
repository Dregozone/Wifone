Web‑Based Voice Calling App – Proof of Concept
Laravel + Reverb + WebRTC (Audio‑Only)
Free Tools Only (except Flux Pro, already installed)

1. Project Name
"Wifone", a play on words with Wifi and Phone. This project is a WebRTC Voice Calling POC

2. Goal
Build a fully functional proof‑of‑concept web application that enables two authenticated users to make real‑time, audio‑only voice calls over Wi‑Fi using WebRTC.
The backend must be Laravel, signalling must use Laravel Reverb, and all components must be free except Flux Pro (already installed).
The system must be simple to set up, easy to deploy on Laravel Forge, and require minimal configuration.

3. Architecture Overview
Frontend
• 	Browser‑based WebRTC audio implementation (JavaScript)
• 	UI elements:
• 	User list with “Call” buttons
• 	Incoming call modal
• 	Accept / Reject buttons
• 	Hang up button
• 	Hidden audio element for remote audio playback
Backend (Laravel)
• 	Laravel 11+
• 	Laravel Breeze for authentication (free)
• 	Laravel Reverb for real‑time signalling (free)
• 	REST endpoints for call actions
• 	Private broadcast channels for user‑to‑user signalling
• 	Optional call logs table
Signalling Layer
Handled entirely through Reverb:
• 	CallInitiated
• 	CallAccepted
• 	CallRejected
• 	CallEnded
• 	WebRTCOffer
• 	WebRTCAnswer
• 	WebRTCIceCandidate
TURN/STUN
Use only free services:
• 	STUN: stun.l.google.com:19302
• 	TURN: free Xirsys tier or free self‑hosted Coturn
(Agent may choose whichever is simplest to implement)
Flux Pro is available but not required for this POC.

4. Functional Requirements
Authentication
• 	Use the already setup Laravel authentication, this is already installed and setup from the livewire skeleton.
• 	Users must be logged in to access the call interface
User Presence
• 	Use Reverb presence channels to show which users are online
Call Flow
1. 	User A clicks “Call” on User B
2. 	Backend broadcasts CallInitiated to User B
3. 	User B sees an incoming call modal
4. 	If accepted:
• 	WebRTC offer/answer exchange begins
• 	ICE candidates exchanged via Reverb
• 	Audio stream established
5. 	Either user may hang up
6. 	CallEnded event is broadcast
WebRTC Requirements
• 	Audio only
• 	Use getUserMedia({ audio: true })
• 	Use RTCPeerConnection with STUN + TURN
• 	Handle:
• 	ontrack
• 	onicecandidate
• 	connectionstatechange

5. Backend Implementation Details
Install and Configure Reverb
Agent must run: php artisan install:broadcasting
php artisan reverb:install
Environment Variables
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=local
REVERB_APP_KEY=local
REVERB_APP_SECRET=local
TURN_URL=turn:your-turn-url
TURN_USERNAME=your-turn-username
TURN_CREDENTIAL=your-turn-password
Broadcast Channels
Define in routes/channels.php:
• 	Private channel: calls.{userId}
• 	Authorize only if authenticated user ID matches the channel userId
Events to Implement
Each event must implement ShouldBroadcast and broadcast on calls.{toUserId}:
• 	CallInitiated
• 	CallAccepted
• 	CallRejected
• 	CallEnded
• 	WebRTCOffer
• 	WebRTCAnswer
• 	WebRTCIceCandidate
Controllers
Create CallController with methods:
• 	initiateCall
• 	acceptCall
• 	rejectCall
• 	endCall
• 	sendOffer
• 	sendAnswer
• 	sendCandidate
Each method:
• 	Validates input
• 	Broadcasts the appropriate event
• 	Returns JSON success

6. Frontend Implementation Details
JavaScript Modules
Create a dedicated WebRTC module that handles:
• 	Microphone access
• 	RTCPeerConnection creation
• 	Adding local audio track
• 	Handling remote audio
• 	Sending ICE candidates to backend
• 	Receiving ICE candidates from Reverb
• 	Creating and handling offers/answers
Reverb Listeners
Listen on: calls.{userId}
Handle:
• 	CallInitiated → show incoming call modal
• 	CallAccepted → begin WebRTC negotiation
• 	WebRTCOffer → set remote description and create answer
• 	WebRTCAnswer → set remote description
• 	WebRTCIceCandidate → add ICE candidate
• 	CallEnded → close connection and reset UI

7. UI Requirements
Main Page
• 	List of online users
• 	“Call” button next to each user
Incoming Call Modal
• 	Shows caller name
• 	Accept / Reject buttons
In‑Call UI
• 	Hang up button
• 	Hidden audio element for remote audio

8. Database Schema (Optional)
Table: calls
Columns:
• 	id
• 	caller_id
• 	receiver_id
• 	started_at
• 	ended_at
• 	status (completed, rejected, missed)

9. Deployment Requirements
Laravel Forge
• 	Deploy Laravel app normally
• 	Enable Reverb in Forge UI
• 	Add TURN credentials to .env
• 	Ensure HTTPS (required for WebRTC)
No paid services required.
Flux Pro is available but not required for this POC.

10. Success Criteria
A successful proof‑of‑concept must:
• 	Allow two authenticated users to see each other online
• 	Allow one user to call another
• 	Show an incoming call modal
• 	Establish a WebRTC audio connection
• 	Allow both users to hear each other clearly
• 	Allow either user to hang up
• 	Work reliably behind NAT using free TURN
• 	Use only free tools except Flux Pro (already installed)
