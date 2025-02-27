<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Start session
ensure_session_started();

// Check if user is logged in
if (!is_logged_in()) {
    header("Location: ../auth/login.php");
    exit;
}

// Check if anonymous users shouldn't have chat
if (isset($_SESSION['is_anonymous']) && $_SESSION['is_anonymous']) {
    header("Location: ../index.php");
    exit;
}

// Get current user ID
$user_id = $_SESSION['user_id'];

// Get conversations for the current user
$stmt = $pdo->prepare("
    SELECT 
        c.conversation_id,
        c.created_at,
        (
            SELECT u.username 
            FROM chat_participants cp
            JOIN users u ON cp.user_id = u.user_id
            WHERE cp.conversation_id = c.conversation_id AND cp.user_id != ?
            LIMIT 1
        ) as other_username,
        (
            SELECT u.profile_picture
            FROM chat_participants cp
            JOIN users u ON cp.user_id = u.user_id
            WHERE cp.conversation_id = c.conversation_id AND cp.user_id != ?
            LIMIT 1
        ) as other_profile_picture,
        (
            SELECT message
            FROM chat_messages
            WHERE conversation_id = c.conversation_id
            ORDER BY created_at DESC
            LIMIT 1
        ) as last_message,
        (
            SELECT media_type
            FROM chat_messages
            WHERE conversation_id = c.conversation_id
            ORDER BY created_at DESC
            LIMIT 1
        ) as last_message_media_type,
        (
            SELECT created_at
            FROM chat_messages
            WHERE conversation_id = c.conversation_id
            ORDER BY created_at DESC
            LIMIT 1
        ) as last_message_time,
        (
            SELECT COUNT(*)
            FROM chat_messages
            WHERE conversation_id = c.conversation_id 
            AND user_id != ? 
            AND is_read = FALSE
        ) as unread_count
    FROM chat_conversations c
    JOIN chat_participants p ON c.conversation_id = p.conversation_id
    WHERE p.user_id = ?
    ORDER BY last_message_time DESC
");
$stmt->execute([$user_id, $user_id, $user_id, $user_id]);
$conversations = $stmt->fetchAll();

// Get conversation ID from URL if provided
$active_conversation = null;
$other_user = null;
$messages = [];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $conversation_id = intval($_GET['id']);
    
    // Check if the user is part of this conversation
    $stmt = $pdo->prepare("
        SELECT * FROM chat_participants
        WHERE conversation_id = ? AND user_id = ?
    ");
    $stmt->execute([$conversation_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        // Get the other user in the conversation
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.username, u.profile_picture
            FROM chat_participants cp
            JOIN users u ON cp.user_id = u.user_id
            WHERE cp.conversation_id = ? AND cp.user_id != ?
        ");
        $stmt->execute([$conversation_id, $user_id]);
        $other_user = $stmt->fetch();
        
        // Get messages for this conversation
        $stmt = $pdo->prepare("
            SELECT m.*, u.username, u.profile_picture
            FROM chat_messages m
            JOIN users u ON m.user_id = u.user_id
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$conversation_id]);
        $messages = $stmt->fetchAll();
        
        // Mark all messages as read
        $stmt = $pdo->prepare("
            UPDATE chat_messages
            SET is_read = TRUE
            WHERE conversation_id = ? AND user_id != ? AND is_read = FALSE
        ");
        $stmt->execute([$conversation_id, $user_id]);
        
        $active_conversation = $conversation_id;
    }
}

include '../includes/header.php';
?>

<style>
/* Chat container styles */
.chat-container {
    height: calc(100vh - 200px);
    background-color: #f0f2f5;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.24);
}

.chat-header {
    padding: 12px 16px;
    background-color: #ffffff;
    border-bottom: 1px solid #e5e5e5;
    display: flex;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 10;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    background-color: #e6ebee;
}

.chat-footer {
    background-color: #ffffff;
    border-top: 1px solid #e5e5e5;
    padding: 8px;
}

/* Message styles */
.message-row {
    display: flex;
    margin-bottom: 12px;
    align-items: flex-end;
}

.message-row.outgoing {
    justify-content: flex-end;
}

.message-avatar {
    margin-right: 8px;
    align-self: flex-end;
}

.message-row.outgoing .message-avatar {
    order: 2;
    margin-right: 0;
    margin-left: 8px;
}

.message-bubble {
    border-radius: 18px;
    padding: 8px 12px;
    max-width: 65%;
    position: relative;
}

.message-row:not(.outgoing) .message-bubble {
    background-color: white;
    border-top-left-radius: 4px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.message-row.outgoing .message-bubble {
    background-color: #e3f2fd;
    border-top-right-radius: 4px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.message-content {
    margin-bottom: 4px;
    word-wrap: break-word;
}

.message-time {
    font-size: 11px;
    color: #8e8e8e;
    text-align: right;
    margin-top: 2px;
}

.message-row.outgoing .message-time {
    color: #89a9d1;
}

/* Media in messages */
.message-media {
    margin-bottom: 4px;
    border-radius: 8px;
    overflow: hidden;
}

.message-media img,
.message-media video {
    max-width: 100%;
    max-height: 300px;
    display: block;
}

.voice-note {
    margin-bottom: 4px;
}

.voice-player {
    width: 200px;
    height: 36px;
    border-radius: 18px;
    background-color: transparent;
}

/* Chat input area */
.chat-input-container {
    display: flex;
    align-items: center;
    background-color: #f0f2f5;
    border-radius: 24px;
    padding: 6px 12px;
    margin: 4px 8px;
}

.chat-input-container textarea {
    flex: 1;
    border: none;
    background-color: transparent;
    resize: none;
    max-height: 100px;
    padding: 8px 12px;
    font-size: 15px;
}

.chat-input-container textarea:focus {
    outline: none;
    box-shadow: none;
}

.chat-input-actions {
    display: flex;
    align-items: center;
}

.chat-input-actions .btn {
    border: none;
    background: transparent;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #7d8e98;
    font-size: 18px;
    padding: 0;
    margin-left: 4px;
    transition: background-color 0.2s;
}

.chat-input-actions .btn:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.send-btn {
    color: #ffffff !important;
    background-color: #1e88e5 !important;
}

.send-btn:hover {
    background-color: #1976d2 !important;
}

/* Voice recording styles */
#voice-recording-container {
    background-color: #fff3cd;
    border-radius: 24px;
    margin: 4px 8px;
}

.pulse {
    display: inline-block;
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(0.95);
        opacity: 0.8;
    }
    50% {
        transform: scale(1.1);
        opacity: 1;
    }
    100% {
        transform: scale(0.95);
        opacity: 0.8;
    }
}

/* Conversation list styles */
.conversation-list {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.24);
}

.conversation-header {
    background-color: #ffffff;
    padding: 16px;
    border-bottom: 1px solid #e5e5e5;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.conversation-item {
    padding: 12px 16px;
    background-color: #ffffff;
    border-bottom: 1px solid #f0f2f5;
    display: flex;
    align-items: center;
    transition: background-color 0.2s;
    text-decoration: none;
    color: inherit;
}

.conversation-item:hover {
    background-color: #f5f7f9;
}

.conversation-item.active {
    background-color: #e3f2fd;
}

.conversation-avatar {
    margin-right: 12px;
    position: relative;
}

.conversation-info {
    flex: 1;
    min-width: 0;
}

.conversation-name {
    font-weight: 500;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conversation-message {
    color: #8e8e8e;
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conversation-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    min-width: 60px;
}

.conversation-time {
    font-size: 12px;
    color: #8e8e8e;
    margin-bottom: 4px;
}

.unread-badge {
    background-color: #1e88e5;
    color: white;
    font-size: 12px;
    min-width: 20px;
    height: 20px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 6px;
}

.new-message-btn {
    background-color: #1e88e5;
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 500;
    transition: background-color 0.2s;
    border: none;
    text-decoration: none;
}

.new-message-btn:hover {
    background-color: #1976d2;
    color: white;
    text-decoration: none;
}

.new-message-btn i {
    margin-right: 8px;
}

/* Empty state styles */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    padding: 24px;
    text-align: center;
}

.empty-state i {
    font-size: 48px;
    color: #bdc3c7;
    margin-bottom: 16px;
}

.empty-state h5 {
    margin-bottom: 8px;
}

.empty-state p {
    color: #8e8e8e;
    margin-bottom: 16px;
}
</style>

<div class="row">
    <div class="col-md-4">
        <div class="conversation-list">
            <div class="conversation-header">
                <h5 class="mb-0">Percakapan</h5>
                <a href="new.php" class="new-message-btn">
                    <i class="fas fa-plus"></i> Baru
                </a>
            </div>
            
            <?php if (count($conversations) > 0): ?>
                <?php foreach ($conversations as $conversation): ?>
                    <a href="index.php?id=<?php echo $conversation['conversation_id']; ?>" 
                        class="conversation-item <?php echo ($active_conversation == $conversation['conversation_id']) ? 'active' : ''; ?>">
                        <div class="conversation-avatar">
                            <img src="/ssipfix/assets/images/<?php echo $conversation['other_profile_picture']; ?>" 
                                class="avatar-md rounded-circle" alt="Profile">
                        </div>
                        <div class="conversation-info">
                            <div class="conversation-name"><?php echo htmlspecialchars($conversation['other_username']); ?></div>
                            <div class="conversation-message">
                                <?php if (isset($conversation['last_message_media_type']) && $conversation['last_message_media_type'] == 'photo'): ?>
                                    <i class="fas fa-image me-1"></i> Foto
                                <?php elseif (isset($conversation['last_message_media_type']) && $conversation['last_message_media_type'] == 'video'): ?>
                                    <i class="fas fa-video me-1"></i> Video
                                <?php elseif (isset($conversation['last_message_media_type']) && $conversation['last_message_media_type'] == 'voice'): ?>
                                    <i class="fas fa-microphone me-1"></i> Pesan Suara
                                <?php else: ?>
                                    <?php echo htmlspecialchars($conversation['last_message']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="conversation-meta">
                            <div class="conversation-time"><?php echo format_date($conversation['last_message_time'], true); ?></div>
                            <?php if ($conversation['unread_count'] > 0): ?>
                                <div class="unread-badge"><?php echo $conversation['unread_count']; ?></div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <h5>Belum ada percakapan</h5>
                    <p>Mulai percakapan baru dengan seseorang</p>
                    <a href="new.php" class="new-message-btn">
                        <i class="fas fa-comment"></i> Mulai Percakapan
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="col-md-8">
        <?php if ($active_conversation): ?>
            <div class="chat-container">
                <div class="chat-header">
                    <img src="/ssipfix/assets/images/<?php echo $other_user['profile_picture']; ?>" 
                         class="avatar-md rounded-circle me-3" alt="Profile">
                    <h5 class="mb-0"><?php echo htmlspecialchars($other_user['username']); ?></h5>
                </div>
                
                <div class="chat-messages" id="chat-messages">
                    <?php foreach ($messages as $message): ?>
                        <div class="message-row <?php echo ($message['user_id'] == $user_id) ? 'outgoing' : ''; ?>">
                            <?php if ($message['user_id'] != $user_id): ?>
                                <div class="message-avatar">
                                    <img src="/ssipfix/assets/images/<?php echo $message['profile_picture']; ?>" 
                                        class="avatar-sm rounded-circle" alt="Profile">
                                </div>
                            <?php endif; ?>
                            
                            <div class="message-bubble">
                                <?php if (!empty($message['message'])): ?>
                                    <div class="message-content"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                <?php endif; ?>
                                
                                <?php if (isset($message['media_type']) && $message['media_type'] == 'photo'): ?>
                                    <div class="message-media">
                                        <img src="/ssipfix/<?php echo $message['media_url']; ?>" alt="Chat media" class="img-fluid">
                                    </div>
                                <?php elseif (isset($message['media_type']) && $message['media_type'] == 'video'): ?>
                                    <div class="message-media">
                                        <video src="/ssipfix/<?php echo $message['media_url']; ?>" controls class="img-fluid"></video>
                                    </div>
                                <?php elseif (isset($message['media_type']) && $message['media_type'] == 'voice'): ?>
                                    <div class="voice-note">
                                        <audio src="/ssipfix/<?php echo $message['media_url']; ?>" controls class="voice-player"></audio>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="message-time"><?php echo date('H:i', strtotime($message['created_at'])); ?></div>
                            </div>
                            
                            <?php if ($message['user_id'] == $user_id): ?>
                                <div class="message-avatar">
                                    <img src="/ssipfix/assets/images/<?php echo $message['profile_picture']; ?>" 
                                        class="avatar-sm rounded-circle" alt="Profile">
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="chat-footer">
                    <form action="send.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="conversation_id" value="<?php echo $active_conversation; ?>">
                        
                        <div class="media-preview mb-2" style="display: none;">
                            <button type="button" class="remove-media-btn"><i class="fas fa-times"></i></button>
                            <div class="media-preview-element"></div>
                        </div>
                        
                        <!-- Voice recording container -->
                        <div id="voice-recording-container" class="mb-2" style="display: none;">
                            <div class="d-flex align-items-center p-2">
                                <div class="voice-wave me-2">
                                    <i class="fas fa-microphone text-danger pulse"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div id="recording-timer" class="text-danger">00:00</div>
                                </div>
                                <button type="button" id="stop-recording" class="btn btn-sm btn-danger me-2">
                                    <i class="fas fa-stop"></i> Berhenti
                                </button>
                                <button type="button" id="cancel-recording" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <input type="hidden" name="voice_data" id="voice-data">
                        </div>
                        
                        <div class="chat-input-container">
                            <textarea name="message" placeholder="Tulis pesan..." rows="1" class="form-control"></textarea>
                            
                            <div class="chat-input-actions">
                                <label for="chat-photo" class="btn" title="Tambah Foto">
                                    <i class="fas fa-image"></i>
                                    <input type="file" id="chat-photo" name="photo" class="file-input" style="display: none;" accept="image/*">
                                </label>
                                
                                <label for="chat-video" class="btn" title="Tambah Video">
                                    <i class="fas fa-video"></i>
                                    <input type="file" id="chat-video" name="video" class="file-input" style="display: none;" accept="video/*">
                                </label>
                                
                                <button type="button" id="start-recording" class="btn" title="Rekam Suara">
                                    <i class="fas fa-microphone"></i>
                                </button>
                                
                                <button type="submit" class="btn send-btn">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="chat-container">
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <h5>Pilih percakapan untuk mulai chat</h5>
                    <p>Atau mulai percakapan baru dengan seseorang</p>
                    <a href="new.php" class="new-message-btn">
                        <i class="fas fa-comment"></i> Mulai Percakapan
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Auto-scroll to bottom of chat messages
    document.addEventListener('DOMContentLoaded', function() {
        const chatMessages = document.getElementById('chat-messages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Make textarea auto-expanding
        const textarea = document.querySelector('.chat-input-container textarea');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        }
    });
</script>

<?php 
// Helper function for formatting date in conversation list
function format_chat_date($date, $short = false) {
    $timestamp = strtotime($date);
    $current_time = time();
    $diff = $current_time - $timestamp;
    
    if ($short) {
        // For conversation list, show only time if today, otherwise show date
        if (date('Y-m-d', $timestamp) === date('Y-m-d')) {
            return date('H:i', $timestamp);
        } else if (date('Y-m-d', $timestamp) === date('Y-m-d', strtotime('-1 day'))) {
            return 'Kemarin';
        } else if (date('Y', $timestamp) === date('Y')) {
            return date('j M', $timestamp);
        } else {
            return date('j/n/y', $timestamp);
        }
    }
    
    if ($diff < 60) {
        return "Baru saja";
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . " menit yang lalu";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " jam yang lalu";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " hari yang lalu";
    } else {
        return date("j F Y", $timestamp);
    }
}

include '../includes/footer.php'; 
?>