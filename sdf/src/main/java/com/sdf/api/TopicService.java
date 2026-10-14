package com.sdf.api;

import com.fasterxml.jackson.databind.JsonNode;

import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

/**
 * Wraps:
 *   GET  /api/groups/{group}/topics   — list topics in a group
 *   POST /api/groups/{group}/topics   — create a topic
 *   POST /api/topics/request-access   — request access to a private topic
 *   POST /api/topics/approve          — approve a pending request
 *   POST /api/topics/warn             — issue a moderation warning
 *
 * NOTE: GroupService already gets a topic list "for free" as a nested
 * array on each group (GET /api/groups eager-loads topics), and screens
 * that only need "which topics does this group have" should keep using
 * that. This service is for the standalone topics endpoint and for the
 * write actions (create/request/approve/warn) that GroupService can't do.
 */
public final class TopicService {

    private TopicService() {}

    public static final class Topic {
        public final long id;
        public final String title;
        public final String description;
        public final boolean isPrivate;

        Topic(long id, String title, String description, boolean isPrivate) {
            this.id = id;
            this.title = title;
            this.description = description;
            this.isPrivate = isPrivate;
        }
    }

    /** GET /api/groups/{group}/topics */
    public static List<Topic> list(long groupId) throws Exception {
        JsonNode json = ApiClient.get().get("/groups/" + groupId + "/topics");
        JsonNode arr = json != null && json.has("data") ? json.get("data") : json;
        List<Topic> result = new ArrayList<>();
        if (arr != null && arr.isArray()) {
            for (JsonNode t : arr) {
                result.add(new Topic(
                        t.path("id").asLong(),
                        t.path("title").asText("Untitled topic"),
                        t.path("description").asText(""),
                        t.path("is_private").asBoolean(false)
                ));
            }
        }
        return result;
    }

    /** POST /api/groups/{group}/topics */
    public static void create(long groupId, String title, String description, boolean isPrivate) throws Exception {
        Map<String, Object> body = new LinkedHashMap<>();
        body.put("title", title);
        body.put("description", description);
        body.put("group_id", groupId);
        body.put("is_private", isPrivate);
        ApiClient.get().post("/groups/" + groupId + "/topics", body);
    }

    /** POST /api/topics/request-access */
    public static void requestAccess(long topicId) throws Exception {
        Map<String, Object> body = new LinkedHashMap<>();
        body.put("topic_id", topicId);
        ApiClient.get().post("/topics/request-access", body);
    }

    /** POST /api/topics/approve */
    public static void approve(long topicId, long userId) throws Exception {
        Map<String, Object> body = new LinkedHashMap<>();
        body.put("topic_id", topicId);
        body.put("user_id", userId);
        ApiClient.get().post("/topics/approve", body);
    }

    /** POST /api/topics/warn */
    public static void warn(long topicId, long userId) throws Exception {
        Map<String, Object> body = new LinkedHashMap<>();
        body.put("topic_id", topicId);
        body.put("user_id", userId);
        ApiClient.get().post("/topics/warn", body);
    }
}
