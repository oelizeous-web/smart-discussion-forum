package com.sdf.screens.student;

import com.sdf.Router;
import com.sdf.api.ApiException;
import com.sdf.api.Async;
import com.sdf.api.QuizService;
import com.sdf.components.Sidebar;
import com.sdf.components.UiKit;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.ScrollPane;
import javafx.scene.layout.*;

import java.util.LinkedHashMap;
import java.util.List;

public class StudentQuizzesScreen {

    public static Parent build() {
        BorderPane root = new BorderPane();
        root.getStyleClass().add("app-shell");

        LinkedHashMap<String, Runnable> links = new LinkedHashMap<>();
        links.put("Dashboard", () -> Router.get().navigate(StudentDashboardScreen::build));
        links.put("Discussions", () -> Router.get().navigate(StudentDiscussionsScreen::build));
        links.put("Notifications", () -> Router.get().navigate(NotificationsScreen::build));
        links.put("Quizzes", null);
        root.setLeft(new Sidebar("Quizzes", com.sdf.session.Session.get().getInitial(), com.sdf.session.Session.get().getName(), links));

        VBox content = new VBox(24);
        content.getStyleClass().add("bg-cream");
        content.setPadding(new Insets(28));

        Label title = new Label("Quiz Center");
        title.setStyle("-fx-font-size: 22px; -fx-font-weight: 800; -fx-text-fill: #18181b;");
        Label sub = new Label("Test your knowledge and track your progress.");
        sub.getStyleClass().add("muted");
        VBox header = new VBox(4, title, sub);

        HBox stats = new HBox(18,
                UiKit.statCard("Quizzes Completed", "\u2026", null),
                UiKit.statCard("Average Score", "\u2026", "-fx-text-fill:#2F7A54; -fx-font-size:28px; -fx-font-weight:800;"),
                UiKit.statCard("Pending / Available", "\u2026", "-fx-text-fill:#ca8a04; -fx-font-size:28px; -fx-font-weight:800;")
        );
        // statCard bakes its value Label in on creation, so grab the actual Labels back out to update them live.
        Label completedValue = (Label) ((VBox) stats.getChildren().get(0)).getChildren().get(1);
        Label avgValue = (Label) ((VBox) stats.getChildren().get(1)).getChildren().get(1);
        Label pendingValue = (Label) ((VBox) stats.getChildren().get(2)).getChildren().get(1);
        for (var n : stats.getChildren()) HBox.setHgrow(n, Priority.ALWAYS);

        HBox middle = new HBox(20);

        VBox availableBox = new VBox(14);
        HBox.setHgrow(availableBox, Priority.ALWAYS);
        Label availableTitle = new Label("Available Now");
        availableTitle.setStyle("-fx-font-size: 16px; -fx-font-weight: 800; -fx-text-fill: #18181b;");
        availableBox.getChildren().add(availableTitle);
        VBox quizList = new VBox(12);
        availableBox.getChildren().add(quizList);

        VBox sideBox = new VBox(18);
        sideBox.setPrefWidth(300);

        VBox recentResults = UiKit.card();
        Label recentTitle = new Label("Recent Results");
        recentTitle.setStyle("-fx-font-weight: 800; -fx-text-fill: #18181b;");
        recentResults.getChildren().add(recentTitle);
        VBox resultsList = new VBox(8);
        recentResults.getChildren().add(resultsList);

        VBox rules = UiKit.card();
        Label rulesTitle = new Label("Quiz Rules & Integrity");
        rulesTitle.setStyle("-fx-font-weight: 800; -fx-text-fill: #18181b;");
        rules.getChildren().add(rulesTitle);
        rules.getChildren().add(ruleLine("Quizzes are timed. The timer cannot be paused once started."));
        rules.getChildren().add(ruleLine("Navigating away from the quiz page may auto-submit your answers."));
        rules.getChildren().add(ruleLine("Scores are final upon submission."));

        sideBox.getChildren().addAll(recentResults, rules);
        middle.getChildren().addAll(availableBox, sideBox);

        Label statusLabel = new Label("Loading quizzes\u2026");
        statusLabel.setStyle("-fx-text-fill: #a1a1aa; -fx-font-size: 12px; -fx-font-style: italic;");

        content.getChildren().addAll(header, stats, statusLabel, middle);
        ScrollPane scroll = new ScrollPane(content);
        scroll.setFitToWidth(true);
        scroll.getStyleClass().add("scroll-pane");
        root.setCenter(scroll);

        Async.run(
                QuizService::list,
                quizzes -> {
                    statusLabel.setVisible(false);
                    statusLabel.setManaged(false);

                    int completed = (int) quizzes.stream().filter(q -> q.attempted).count();
                    int pending = quizzes.size() - completed;
                    completedValue.setText(String.valueOf(completed));
                    pendingValue.setText(String.valueOf(pending));

                    List<Integer> numericScores = quizzes.stream()
                            .map(q -> q.scoreText)
                            .filter(s -> s != null && s.replace("%", "").matches("\\d+"))
                            .map(s -> Integer.parseInt(s.replace("%", "")))
                            .toList();
                    avgValue.setText(numericScores.isEmpty() ? "\u2014"
                            : (int) numericScores.stream().mapToInt(Integer::intValue).average().orElse(0) + "%");

                    quizList.getChildren().clear();
                    if (quizzes.isEmpty()) {
                        Label empty = new Label("No quizzes have been published to your groups yet.");
                        empty.setStyle("-fx-text-fill: #a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");
                        quizList.getChildren().add(empty);
                    } else {
                        for (QuizService.QuizSummary q : quizzes) {
                            quizList.getChildren().add(quizRow(q));
                        }
                    }

                    resultsList.getChildren().clear();
                    List<QuizService.QuizSummary> results = quizzes.stream().filter(q -> q.attempted).toList();
                    if (results.isEmpty()) {
                        Label empty = new Label("No completed quizzes yet.");
                        empty.setStyle("-fx-text-fill: #a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");
                        resultsList.getChildren().add(empty);
                    } else {
                        for (QuizService.QuizSummary q : results) {
                            resultsList.getChildren().add(resultRow(q));
                        }
                    }
                },
                err -> {
                    statusLabel.setText("Couldn't load quizzes: " + friendlyMessage(err));
                    statusLabel.setStyle("-fx-text-fill: #dc2626; -fx-font-size: 12px;");
                }
        );

        return root;
    }

    private static HBox quizRow(QuizService.QuizSummary q) {
        HBox row = new HBox();
        row.getStyleClass().add("card");
        row.setAlignment(Pos.CENTER_LEFT);

        VBox text = new VBox(6);
        HBox nameRow = new HBox(8);
        nameRow.setAlignment(Pos.CENTER_LEFT);
        Label n = new Label(q.title);
        n.setStyle("-fx-font-weight: 800; -fx-font-size: 15px; -fx-text-fill: #18181b;");
        nameRow.getChildren().add(n);
        if ("DRAFT".equalsIgnoreCase(q.status)) {
            nameRow.getChildren().add(UiKit.badge("Draft", "badge-outline-orange"));
        }
        StringBuilder meta = new StringBuilder();
        if (!q.groupLabel.isBlank()) meta.append(q.groupLabel);
        if (q.timeLimitMinutes != null) {
            if (meta.length() > 0) meta.append("  \u00B7  ");
            meta.append(q.timeLimitMinutes).append(" min");
        }
        if (q.startTime != null && !q.startTime.isBlank()) {
            if (meta.length() > 0) meta.append("  ·  ");
            meta.append("Opens ").append(formatToLocalTime(q.startTime));
        }
        Label m = new Label(meta.toString());
        m.getStyleClass().add("muted");
        m.setStyle("-fx-font-size: 12px;");
        text.getChildren().addAll(nameRow, m);

        Region sp = new Region();
        HBox.setHgrow(sp, Priority.ALWAYS);
        Button btn;
        if (q.attempted) {
            btn = new Button(q.scoreText != null ? "Completed \u00B7 " + q.scoreText : "Completed");
            btn.getStyleClass().add("btn-outline");
            btn.setDisable(true);
        } else if ("DRAFT".equalsIgnoreCase(q.status)) {
            btn = new Button("Not Published Yet");
            btn.getStyleClass().add("btn-outline");
            btn.setDisable(true);
        } else {
            btn = new Button("Start Quiz");
            btn.getStyleClass().add("btn-primary");
            btn.setOnAction(e -> Router.get().navigate(() -> QuizAttemptScreen.build(q.id, q.title)));
        }

        row.getChildren().addAll(text, sp, btn);
        return row;
    }

    private static HBox resultRow(QuizService.QuizSummary q) {
        HBox row = new HBox();
        row.setStyle("-fx-background-color:#fafafa; -fx-border-color:#e4e4e7; -fx-border-radius:10; -fx-background-radius:10; -fx-padding:10;");
        row.setAlignment(Pos.CENTER_LEFT);
        VBox text = new VBox(2);
        Label n = new Label(q.title);
        n.setStyle("-fx-font-weight: 700; -fx-font-size: 12px; -fx-text-fill: #27272a;");
        text.getChildren().add(n);
        Region sp = new Region();
        HBox.setHgrow(sp, Priority.ALWAYS);
        Label s = new Label(q.scoreText != null ? q.scoreText : "Submitted");
        s.setStyle("-fx-font-weight: 800; -fx-font-size: 13px; -fx-text-fill: #2F7A54;");
        row.getChildren().addAll(text, sp, s);
        return row;
    }

    private static HBox ruleLine(String text) {
        Label bullet = new Label("\u2022");
        bullet.setStyle("-fx-text-fill: #ef4444;");
        Label t = new Label(text);
        t.setWrapText(true);
        t.getStyleClass().add("muted");
        t.setStyle("-fx-font-size: 12px;");
        HBox row = new HBox(8, bullet, t);
        return row;
    }

    private static String friendlyMessage(Exception err) {
        if (err instanceof ApiException ae) return ae.firstMessage();
        return "Couldn't reach the server. Check your connection and try again.";
    }

    private static String formatToLocalTime(String isoDateTimeStr) {
        try {
            // Parse the UTC ISO timestamp from the server
            java.time.Instant instant = java.time.Instant.parse(isoDateTimeStr);

            // Convert to the user's local system time zone
            java.time.ZonedDateTime localDateTime = instant.atZone(java.time.ZoneId.systemDefault());

            // Format nicely (e.g. "28 Jul, 05:52 PM")
            java.time.format.DateTimeFormatter formatter =
                    java.time.format.DateTimeFormatter.ofPattern("dd MMM, hh:mm a");

            return localDateTime.format(formatter);
        } catch (Exception e) {
            // Fallback to raw string if parsing fails
            return isoDateTimeStr;
        }
    }
}
