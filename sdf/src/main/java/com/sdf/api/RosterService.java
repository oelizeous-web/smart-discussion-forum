package com.sdf.api;

import com.fasterxml.jackson.databind.JsonNode;

import java.util.ArrayList;
import java.util.List;

/**
 * Wraps the real, group-scoped roster/gradebook endpoints added to the
 * backend alongside this client change:
 *   GET /api/groups/{group}/students  -> GroupController@students
 *   GET /api/groups/{group}/grades    -> GroupController@grades
 *
 * Both return real database rows (group_members + the blacklist table for
 * standing, and the marks table for scores) instead of the previous
 * moderation-only workaround.
 */
public final class RosterService {

    private RosterService() {}

    public static final class StudentRow {
        public final long id;
        public final String name;
        public final String email;
        public final String accountStatus;
        public final int warningCount;
        public final int quizzesTaken;
        public final Double averageScore;
        public final String standing;
        public final int postsCount;
        public final int quizzesCompleted;
        public final int participationScore;

        StudentRow(long id, String name, String email, String accountStatus, int warningCount,
                int quizzesTaken, Double averageScore, String standing,
                int postsCount, int quizzesCompleted, int participationScore) {
            this.id = id;
            this.name = name;
            this.email = email;
            this.accountStatus = accountStatus;
            this.warningCount = warningCount;
            this.quizzesTaken = quizzesTaken;
            this.averageScore = averageScore;
            this.standing = standing;
            this.postsCount = postsCount;
            this.quizzesCompleted = quizzesCompleted;
            this.participationScore = participationScore;
        }
    }

    public static final class GradeRow {
        public final long userId;
        public final String studentName;
        public final long quizId;
        public final String quizTitle;
        public final int score;
        public final String grade;
        public final String completionDate;

        GradeRow(long userId, String studentName, long quizId, String quizTitle, int score,
                 String grade, String completionDate) {
            this.userId = userId;
            this.studentName = studentName;
            this.quizId = quizId;
            this.quizTitle = quizTitle;
            this.score = score;
            this.grade = grade;
            this.completionDate = completionDate;
        }
    }

    /** GET /api/groups/{group}/students */
    public static List<StudentRow> students(long groupId) throws Exception {
        JsonNode json = ApiClient.get().get("/groups/" + groupId + "/students");
        List<StudentRow> out = new ArrayList<>();
        JsonNode arr = json != null ? json.get("data") : null;
        if (arr == null || !arr.isArray()) return out;
        for (JsonNode n : arr) {
            Double avg = n.hasNonNull("average_score") ? n.get("average_score").asDouble() : null;
        out.add(new StudentRow(
            n.path("id").asLong(0L),
            n.path("name").asText(),
            n.path("email").asText(),
            n.hasNonNull("account_status") ? n.get("account_status").asText() : "ACTIVE",
            n.path("warning_count").asInt(0),
            n.path("quizzes_taken").asInt(0),
            avg,
            n.path("standing").asText(), 
            n.path("posts_count").asInt(0),
            n.path("quizzes_completed").asInt(0),
            n.path("participation_score").asInt(0)
        ));
        }
        return out;
    }

    /** GET /api/groups/{group}/grades */
    public static List<GradeRow> grades(long groupId) throws Exception {
        JsonNode json = ApiClient.get().get("/groups/" + groupId + "/grades");
        List<GradeRow> out = new ArrayList<>();
        JsonNode arr = json != null ? json.get("data") : null;
        if (arr == null || !arr.isArray()) return out;
        for (JsonNode n : arr) {
            out.add(new GradeRow(
                    n.path("user_id").asLong(),
                    n.path("student_name").asText(""),
                    n.path("quiz_id").asLong(),
                    n.path("quiz_title").asText(""),
                    n.path("score").asInt(0),
                    n.path("grade").asText(""),
                    n.path("completion_date").asText("")
            ));
        }
        return out;
    }
}
