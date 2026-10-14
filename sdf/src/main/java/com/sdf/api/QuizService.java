package com.sdf.api;

import com.fasterxml.jackson.databind.JsonNode;

import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

public final class QuizService {

    private QuizService() {}

    // ---- shapes used when a lecturer CREATES a quiz -------------

    public static final class QuestionInput {
        public final String text;
        public final int points;
        public final String correctAnswer; // "A" | "B" | "C" | "D"
        public final Map<String, String> options; // keys A,B,C,D

        public QuestionInput(String text, int points, String correctAnswer, Map<String, String> options) {
            this.text = text;
            this.points = points;
            this.correctAnswer = correctAnswer;
            this.options = options;
        }
    }

    /** POST /api/groups/{group}/quizzes */
    public static void create(long groupId, String title, String description, int timeLimitMinutes,
                               String status, String startTime, boolean autoSubmit,
                               List<QuestionInput> questions) throws Exception {
        Map<String, Object> body = new LinkedHashMap<>();
        body.put("title", title);
        body.put("description", description);
        body.put("time_limit", timeLimitMinutes);
        body.put("status", status);
        if (startTime != null && !startTime.isBlank()) body.put("start_time", startTime);
        body.put("auto_submit", autoSubmit);

        List<Map<String, Object>> qList = new ArrayList<>();
        for (QuestionInput q : questions) {
            Map<String, Object> qBody = new LinkedHashMap<>();
            qBody.put("text", q.text);
            qBody.put("points", q.points);
            qBody.put("correct_answer", q.correctAnswer);
            qBody.put("options", q.options);
            qList.add(qBody);
        }
        body.put("questions", qList);

        ApiClient.get().post("/groups/" + groupId + "/quizzes", body);
    }

    // ---- shapes used when READING quizzes back -------------------------

    public static final class QuizSummary {
        public final long id;
        public final String title;
        public final String description;
        public final String status;
        public final String groupLabel;
        public final Integer timeLimitMinutes;
        public final String startTime;
        public final boolean attempted;
        public final String scoreText;

        QuizSummary(long id, String title, String description, String status, String groupLabel,
                    Integer timeLimitMinutes, String startTime, boolean attempted, String scoreText) {
            this.id = id;
            this.title = title;
            this.description = description;
            this.status = status;
            this.groupLabel = groupLabel;
            this.timeLimitMinutes = timeLimitMinutes;
            this.startTime = startTime;
            this.attempted = attempted;
            this.scoreText = scoreText;
        }
    }

    /** GET /api/quizzes — quizzes available to the current user (student) or created by them (lecturer/admin). */
    public static List<QuizSummary> list() throws Exception {
        JsonNode json = ApiClient.get().get("/quizzes");
        JsonNode arr = json != null && json.has("data") ? json.get("data") : json;
        List<QuizSummary> result = new ArrayList<>();
        if (arr != null && arr.isArray()) {
            for (JsonNode q : arr) {
                result.add(parseSummary(q));
            }
        }
        return result;
    }

    private static QuizSummary parseSummary(JsonNode q) {
        long id = q.path("id").asLong();
        String title = firstText(q, "title", "name");
        String description = firstText(q, "description", "instructions");
        String status = firstText(q, "status");
        Integer timeLimit = q.has("time_limit") && !q.get("time_limit").isNull() ? q.get("time_limit").asInt() : null;
        String startTime = firstTextOrNull(q, "start_time", "starts_at");

        String groupLabel = "";
        if (q.has("group") && q.get("group").isObject()) {
            groupLabel = q.get("group").path("name").asText("");
        } else if (q.has("group_name")) {
            groupLabel = q.get("group_name").asText("");
        } else if (q.has("group_id")) {
            groupLabel = "Group #" + q.get("group_id").asText("");
        }

        boolean attempted = false;
        String scoreText = null;
        if (q.has("attempted")) attempted = q.get("attempted").asBoolean(false);
        if (q.has("has_attempted")) attempted = attempted || q.get("has_attempted").asBoolean(false);
        if (q.has("completed")) attempted = attempted || q.get("completed").asBoolean(false);

        JsonNode attemptNode = q.has("attempt") ? q.get("attempt") : q.has("my_attempt") ? q.get("my_attempt") : null;
        if (attemptNode != null && attemptNode.isObject()) {
            attempted = true;
            if (attemptNode.has("score")) {
                String score = attemptNode.get("score").asText();
                String total = attemptNode.has("total_points") ? attemptNode.get("total_points").asText() : null;
                scoreText = total != null ? score + "/" + total : score;
            } else if (attemptNode.has("percentage")) {
                scoreText = attemptNode.get("percentage").asText() + "%";
            }
        }
        if (scoreText == null && q.has("score")) {
            attempted = true;
            scoreText = q.get("score").asText();
        }

        return new QuizSummary(id, title, description, status, groupLabel, timeLimit, startTime, attempted, scoreText);
    }

    // ---- single quiz detail, used to render the attempt screen ----------

    public static final class QuestionDetail {
        public final long id;
        public final String text;
        public final int points;
        public final Map<String, String> options; // A,B,C,D -> text, in that order

        QuestionDetail(long id, String text, int points, Map<String, String> options) {
            this.id = id;
            this.text = text;
            this.points = points;
            this.options = options;
        }
    }

    public static final class QuizDetail {
        public final long id;
        public final String title;
        public final String description;
        public final Integer timeLimitMinutes;
        public final String serverTime;
        public final String endsAt;
        public final Long attemptId;
        public final boolean blocked;
        public final String blockedMessage;
        public final List<QuestionDetail> questions;
        public final String rawJson;

        QuizDetail(long id, String title, String description, Integer timeLimitMinutes, String serverTime,
                String endsAt, Long attemptId, boolean blocked, String blockedMessage,
                List<QuestionDetail> questions, String rawJson) {
            this.id = id;
            this.title = title;
            this.description = description;
            this.timeLimitMinutes = timeLimitMinutes;
            this.serverTime = serverTime;
            this.endsAt = endsAt;
            this.attemptId = attemptId;
            this.blocked = blocked;
            this.blockedMessage = blockedMessage;
            this.questions = questions;
            this.rawJson = rawJson;
        }
    }

    /** GET /api/quizzes/{quiz} */
    public static QuizDetail getDetail(long quizId) throws Exception {
        JsonNode raw = ApiClient.get().get("/quizzes/" + quizId);
        JsonNode root = raw != null && raw.has("data") ? raw.get("data") : raw;

        long id = root.path("id").asLong(quizId);
        String title = firstText(root, "title", "name");
        String description = firstText(root, "description", "instructions");
        Integer timeLimit = root.has("time_limit") && !root.get("time_limit").isNull() ? root.get("time_limit").asInt() : null;
        String serverTime = firstTextOrNull(root, "server_time");
        String endsAt = firstTextOrNull(root, "ends_at");
        boolean blocked = root.path("blocked").asBoolean(false);
        String blockedMessage = firstTextOrNull(root, "message");

        // title/description/time_limit/questions actually live under "quiz" —
        // fall back to reading through it if the top-level fields came up empty.
        JsonNode quizNode = root.has("quiz") && root.get("quiz").isObject() ? root.get("quiz") : root;
        if (title.isEmpty()) title = firstText(quizNode, "title", "name");
        if (description.isEmpty()) description = firstText(quizNode, "description", "instructions");
        if (timeLimit == null && quizNode.has("time_limit") && !quizNode.get("time_limit").isNull()) {
            timeLimit = quizNode.get("time_limit").asInt();
        }

        Long attemptId = null;
        if (root.has("attempt_id") && !root.get("attempt_id").isNull()) {
            attemptId = root.get("attempt_id").asLong();
        } else if (root.has("attempt") && root.get("attempt").has("id")) {
            attemptId = root.get("attempt").get("id").asLong();
        } else if (root.has("current_attempt") && root.get("current_attempt").has("id")) {
            attemptId = root.get("current_attempt").get("id").asLong();
        }

        List<QuestionDetail> questions = new ArrayList<>();
        JsonNode qArr = quizNode.get("questions");
        if (qArr != null && qArr.isArray()) {
            for (JsonNode qn : qArr) {
                long qId = qn.path("id").asLong();
                String qText = firstText(qn, "text", "question");
                int points = qn.path("points").asInt(1);
                Map<String, String> options = new LinkedHashMap<>();
                JsonNode opts = qn.get("options");
                if (opts != null && opts.isObject()) {
                    for (String key : new String[]{"A", "B", "C", "D"}) {
                        if (opts.has(key)) options.put(key, opts.get(key).asText(""));
                    }
                } else if (opts != null && opts.isArray()) {
                    String[] letters = {"A", "B", "C", "D"};
                    int i = 0;
                    for (JsonNode o : opts) {
                        if (i >= letters.length) break;
                        options.put(letters[i], o.asText(""));
                        i++;
                    }
                }
                questions.add(new QuestionDetail(qId, qText, points, options));
            }
        }

        return new QuizDetail(id, title, description, timeLimit, serverTime, endsAt, attemptId,
                blocked, blockedMessage, questions, root.toString());
    }

    /** GET /api/quizzes/{quiz}/report */
    public static JsonNode report(long quizId) throws Exception {
        return ApiClient.get().get("/quizzes/" + quizId + "/report");
    }

    // ---- helpers ---------------------------------------------------------

    private static String firstText(JsonNode node, String... keys) {
        for (String k : keys) {
            if (node.has(k) && !node.get(k).isNull()) return node.get(k).asText("");
        }
        return "";
    }

    private static String firstTextOrNull(JsonNode node, String... keys) {
        for (String k : keys) {
            if (node.has(k) && !node.get(k).isNull()) return node.get(k).asText(null);
        }
        return null;
    }
}
