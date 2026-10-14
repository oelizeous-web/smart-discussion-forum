package com.sdf.api;

import com.fasterxml.jackson.databind.JsonNode;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;

public final class NotificationService {
    private NotificationService() {}

    public static final class Notif {
        public final String id;
        public final long groupId, topicId, postId;
        public final String message;
        public final boolean read;
        /** Raw "Y-m-d H:i:s" string straight off Carbon::toDateTimeString() — see NotificationController@index. */
        public final String createdAt;
        Notif(String id, long groupId, long topicId, long postId, String message, boolean read, String createdAt) {
            this.id = id; this.groupId = groupId; this.topicId = topicId; this.postId = postId;
            this.message = message; this.read = read; this.createdAt = createdAt;
        }
    }

    public static List<Notif> list() throws Exception {
        JsonNode json = ApiClient.get().get("/notifications");
        List<Notif> out = new ArrayList<>();
        for (JsonNode n : json.path("data")) {
            out.add(new Notif(
                    n.path("id").asText(),
                    n.path("group_id").asLong(-1),
                    n.path("topic_id").asLong(-1),
                    n.path("post_id").asLong(-1),
                    n.path("message").asText(),
                    n.path("read").asBoolean(false),
                    n.hasNonNull("created_at") ? n.get("created_at").asText() : null
            ));
        }
        return out;
    }

    public static int unreadCount() throws Exception {
        return ApiClient.get().get("/notifications").path("unread_count").asInt(0);
    }

    private static final DateTimeFormatter CREATED_AT_FMT = DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss");

    /**
     * Unread group-message notifications from the last {@code days} days —
     * powers the Dashboard's "Unread Msgs" stat. Every notification the
     * backend creates is a new_reply on a topic in one of the student's
     * groups (see NewReplyNotification), so "unread" here already means
     * "group messages you haven't opened yet". An unparsable/missing
     * timestamp is counted as recent rather than silently dropped, so a
     * backend formatting hiccup never hides a real unread message.
     */
    public static int unreadCountWithinDays(int days) throws Exception {
        LocalDateTime cutoff = LocalDateTime.now().minusDays(days);
        int count = 0;
        for (Notif n : list()) {
            if (n.read) continue;
            if (n.createdAt == null || n.createdAt.isBlank()) { count++; continue; }
            try {
                LocalDateTime createdAt = LocalDateTime.parse(n.createdAt, CREATED_AT_FMT);
                if (!createdAt.isBefore(cutoff)) count++;
            } catch (Exception e) {
                count++;
            }
        }
        return count;
    }

    public static void markRead(String id) throws Exception {
        ApiClient.get().post("/notifications/" + id + "/read", Map.of());
    }
}