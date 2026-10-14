package com.sdf.api;

import com.fasterxml.jackson.databind.JsonNode;

import java.util.LinkedHashMap;
import java.util.Map;

public final class AttemptService {

    private AttemptService() {}

    public static final class SubmitResult {
        public final String scoreText;   //  may be null
        public final String message;     // best-effort server message, may be null
        public final String rawJson;

        SubmitResult(String scoreText, String message, String rawJson) {
            this.scoreText = scoreText;
            this.message = message;
            this.rawJson = rawJson;
        }
    }

    /** POST /api/attempts/{attempt}/submit — answers keyed by question id, e.g. {"1":"A","2":"C"}. */
    public static SubmitResult submit(long attemptId, Map<Long, String> answers, boolean autoSubmitted) throws Exception {
        Map<String, Object> answerBody = new LinkedHashMap<>();
        for (Map.Entry<Long, String> e : answers.entrySet()) {
            answerBody.put(String.valueOf(e.getKey()), e.getValue());
        }
        Map<String, Object> body = new LinkedHashMap<>();
        body.put("answers", answerBody);
        body.put("auto_submitted", autoSubmitted);

        JsonNode json = ApiClient.get().post("/attempts/" + attemptId + "/submit", body);
        if (json == null) return new SubmitResult(null, null, "");

        String scoreText = null;
        if (json.has("score")) {
            String score = json.get("score").asText();
            String total = json.has("total_points") ? json.get("total_points").asText()
                    : json.has("total") ? json.get("total").asText() : null;
            scoreText = total != null ? score + "/" + total : score;
        } else if (json.has("percentage")) {
            scoreText = json.get("percentage").asText() + "%";
        }
        String message = json.has("message") ? json.get("message").asText() : null;

        return new SubmitResult(scoreText, message, json.toString());
    }
}
