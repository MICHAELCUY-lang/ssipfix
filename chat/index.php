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

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Percakapan</h5>
                <a href="new.php" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus"></i> Baru
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (count($conversations) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($conversations as $conversation): ?>
                            <a href="index.php?id=<?php echo $conversation['conversation_id']; ?>" 
                               class="list-group-item list-group-item-action <?php echo ($active_conversation == $conversation['conversation_id']) ? 'active' : ''; ?>">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <img src="/ssipfix/assets/images/<?php echo $conversation['other_profile_picture']; ?>" 
                                             class="avatar-md rounded-circle me-3" alt="Profile">
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($conversation['other_username']); ?></h6>
                                            <p class="mb-0 small text-truncate" style="max-width: 150px;">
                                                <?php echo htmlspecialchars($conversation['last_message']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <small><?php echo format_date($conversation['last_message_time']); ?></small>
                                        <?php if ($conversation['unread_count'] > 0): ?>
                                            <span class="badge bg-primary rounded-pill"><?php echo $conversation['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center p-4">
                        <p class="text-muted">Belum ada percakapan</p>
                        <a href="new.php" class="btn btn-primary">
                            <i class="fas fa-comment"></i> Mulai Percakapan
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <?php if ($active_conversation): ?>
            <div class="card">
                <div class="card-header bg-light">
                    <div class="d-flex align-items-center">
                        <img src="/ssipfix/assets/images/<?php echo $other_user['profile_picture']; ?>" 
                             class="avatar-md rounded-circle me-3" alt="Profile">
                        <h5 class="mb-0"><?php echo htmlspecialchars($other_user['username']); ?></h5>
                    </div>
                </div>
                <div class="card-body" style="height: 400px; overflow-y: auto;" id="chat-messages">
                    <?php foreach ($messages as $message): ?>
                        <div class="d-flex mb-3 <?php echo ($message['user_id'] == $user_id) ? 'justify-content-end' : 'justify-content-start'; ?>">
                            <?php if ($message['user_id'] != $user_id): ?>
                                <img src="/ssipfix/assets/images/<?php echo $message['profile_picture']; ?>" 
                                     class="avatar-sm rounded-circle me-2 align-self-end" alt="Profile">
                            <?php endif; ?>
                            
                            <div class="card <?php echo ($message['user_id'] == $user_id) ? 'bg-primary text-white' : 'bg-light'; ?>" 
                                 style="max-width: 75%; border-radius: 15px;">
                                <div class="card-body py-2 px-3">
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                                    <small class="<?php echo ($message['user_id'] == $user_id) ? 'text-white-50' : 'text-muted'; ?> d-block text-end">
                                        <?php echo date('H:i', strtotime($message['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <?php if ($message['user_id'] == $user_id): ?>
                                <img src="/ssipfix/assets/images/<?php echo $message['profile_picture']; ?>" 
                                     class="avatar-sm rounded-circle ms-2 align-self-end" alt="Profile">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer">
                    <form action="send.php" method="post">
                        <input type="hidden" name="conversation_id" value="<?php echo $active_conversation; ?>">
                        <div class="input-group">
                            <textarea name="message" class="form-control" placeholder="Tulis pesan..." rows="1" required></textarea>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                    <h5>Pilih percakapan untuk mulai chat</h5>
                    <p class="text-muted">Atau mulai percakapan baru dengan seseorang</p>
                    <a href="new.php" class="btn btn-primary">
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
    });
</script>

<?php include '../includes/footer.php'; ?>