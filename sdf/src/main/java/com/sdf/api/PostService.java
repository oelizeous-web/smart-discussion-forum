package com.sdf.api;

import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.sdf.service.SyncService;
import com.sdf.storage.TokenStorage;

import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

/**
 * Wraps /api/topics/{topic}/posts.
 *
 * PostController returns PostResource objects, which Laravel wraps
 * in a top-level "data" key by default.
 *
 * Fields include:
 * id, author_name, topic_id, content, receiver_id and timestamp.
 */
public final class PostService {

    private static final ObjectMapper OBJECT_MAPPER =
            new ObjectMapper();

    private PostService() {
    }

    public static final class Post {

        public final long id;
        public final String authorName;
        public final String content;
        public final String timestamp;

        Post(
                long id,
                String authorName,
                String content,
                String timestamp
        ) {
            this.id = id;
            this.authorName = authorName;
            this.content = content;
            this.timestamp = timestamp;
        }
    }

    /**
     * Fetches all posts belonging to a topic.
     */
   public static final class PostsResult {
    public final List<Post> posts;
    public final boolean fromCache;

    PostsResult(List<Post> posts, boolean fromCache) {
        this.posts = posts;
        this.fromCache = fromCache;
    }
}

        public static PostsResult list(long topicId) throws Exception {
        try {
                JsonNode json = ApiClient.get().get("/topics/" + topicId + "/posts");
                JsonNode postsArray = json.has("data") ? json.get("data") : json;

                List<Post> posts = new ArrayList<>();
                List<com.sdf.storage.MessageCache.CachedPost> toCache = new ArrayList<>();

                if (postsArray != null && postsArray.isArray()) {
                for (JsonNode postNode : postsArray) {
                        String authorName = postNode.has("author_name") ? postNode.get("author_name").asText() : "Unknown";
                        String content = postNode.has("content") ? postNode.get("content").asText() : "";
                        String timestamp = postNode.has("timestamp") ? postNode.get("timestamp").asText() : "";
                        long id = postNode.path("id").asLong();

                        posts.add(new Post(id, authorName, content, timestamp));
                        toCache.add(new com.sdf.storage.MessageCache.CachedPost(id, authorName, content, timestamp));
                }
                }

                com.sdf.storage.MessageCache.replace(topicId, toCache);
                return new PostsResult(posts, false);

        } catch (java.io.IOException networkFailure) {
                // Genuinely offline — ApiException (real server response) and
                // InterruptedException still propagate normally; only a true
                // connectivity failure falls back to whatever we last saw.
                List<Post> cached = new ArrayList<>();
                for (com.sdf.storage.MessageCache.CachedPost c : com.sdf.storage.MessageCache.read(topicId)) {
                cached.add(new Post(c.id, c.authorName, c.content, c.timestamp));
                }
                return new PostsResult(cached, true);
        }
        }
    /**
     * Sends a post.
     *
     * If the server is unavailable, SyncService stores the request
     * locally so that it can be synchronized later.
     */
    public static void send(
            long topicId,
            String content
    ) throws Exception {

        Map<String, Object> body =
                new LinkedHashMap<>();

        body.put("content", content);

        String endpoint =
                "/topics/" + topicId + "/posts";

        String payload =
                OBJECT_MAPPER.writeValueAsString(body);

        String token =
                TokenStorage.getToken();

        SyncService.sendPost(
                endpoint,
                payload,
                token
        );
    }

    /**
     * Flags a post.
     * POST /api/posts/{post}/flag
     */
    public static void flag(
            long postId,
            String reason
    ) throws Exception {

        Map<String, Object> body =
                new LinkedHashMap<>();

        body.put("reason", reason);

        ApiClient.get().post(
                "/posts/" + postId + "/flag",
                body
        );
    }
}