package com.sdf.api;

import com.fasterxml.jackson.databind.JsonNode;

import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

/**
 * Wraps the /api/groups endpoints.
 *
 * IMPORTANT: GroupController@index returns raw Eloquent models (not the
 * GroupResource class, which exists in the codebase but isn't actually
 * used here) — so the real JSON fields are the raw DB columns: id, name,
 * user_id, created_at. Confirmed against GroupService.php on the backend.
 *
 * getUserGroups() eager-loads topics, so each "my_groups" entry includes
 * a nested "topics" array — that's how we get a topic id to open without
 * needing GET /api/groups/{group}/topics. As of the privacy fix, each
 * nested topic also carries is_private/can_access/participant_status,
 * annotated server-side per-user — same fields the web client's Blade
 * view reads off this same getUserGroups() collection, so desktop and
 * web now agree on what's locked and what isn't.
 */
public final class GroupService {

    private GroupService() {}

    public static final class Topic {
        public final long id;
        public final String title;
        public final boolean isPrivate;
        public final boolean canAccess;
        public final String participantStatus;

        Topic(long id, String title, boolean isPrivate, boolean canAccess, String participantStatus) {
            this.id = id;
            this.title = title;
            this.isPrivate = isPrivate;
            this.canAccess = canAccess;
            this.participantStatus = participantStatus;
        }
    }

    public static final class Group {
        public final long id;
        public final String name;
        public final List<Topic> topics;
        public final int topicCount;

        Group(long id, String name, List<Topic> topics, int topicCount) {
            this.id = id;
            this.name = name;
            this.topics = topics;
            this.topicCount =topicCount;
        }
    }

    /** GET /api/groups/manageable — every group, for lecturer/admin management screens. */
    public static List<Group> listManageable() throws Exception {
        JsonNode json = ApiClient.get().get("/groups/manageable");
        return parseList(json.get("data"));
    }

    public static final class GroupsResult {
        public final List<Group> myGroups;
        public final List<Group> availableGroups;

        GroupsResult(List<Group> myGroups, List<Group> availableGroups) {
            this.myGroups = myGroups;
            this.availableGroups = availableGroups;
        }
    }

    /** GET /api/groups — returns both the user's groups and groups they can still join. */
    public static GroupsResult list() throws Exception {
        JsonNode json = ApiClient.get().get("/groups");
        return new GroupsResult(parseList(json.get("my_groups")), parseList(json.get("available_groups")));
    }

    /** POST /api/groups/join */
    public static void join(long groupId) throws Exception {
        Map<String, Object> body = new LinkedHashMap<>();
        body.put("group_id", groupId);
        ApiClient.get().post("/groups/join", body);
    }

    /** POST /api/groups — creates a group plus its first topic. */
    public static void create(String name, String topicTitle, String topicDescription) throws Exception {
        Map<String, Object> body = new LinkedHashMap<>();
        body.put("name", name);
        body.put("topic_title", topicTitle);
        body.put("topic_description", topicDescription);
        ApiClient.get().post("/groups", body);
    }

    private static List<Group> parseList(JsonNode arr) {
        List<Group> result = new ArrayList<>();
        if (arr == null || !arr.isArray()) return result;
        for (JsonNode g : arr) {
            long id = g.path("id").asLong();
            String name = g.path("name").asText("Untitled group");
            List<Topic> topics = new ArrayList<>();
            JsonNode topicsNode = g.get("topics");
            if (topicsNode != null && topicsNode.isArray()) {
                for (JsonNode t : topicsNode) {
                    boolean isPrivate = t.path("is_private").asBoolean(false);
                    boolean canAccess = t.path("can_access").asBoolean(true);
                    String participantStatus = t.hasNonNull("participant_status")
                            ? t.get("participant_status").asText() : null;


// Topics created via the lecturer dashboard's

                    String rawTitle = t.path("title").asText("");
                    String rawDescription = t.path("description").asText("");
                    String displayTitle = !rawTitle.isBlank() ? rawTitle
                            : (!rawDescription.isBlank() ? rawDescription : "Untitled topic");

                    topics.add(new Topic(
                            t.path("id").asLong(),
                            t.path("title").asText("Untitled topic"),
                            isPrivate, canAccess, participantStatus
                    ));
                }
            }
            result.add(new Group(id, name, topics ,g.has("topic_count") ? g.path("topic_count").asInt() : topics.size()));
        }
        return result;
    }
}