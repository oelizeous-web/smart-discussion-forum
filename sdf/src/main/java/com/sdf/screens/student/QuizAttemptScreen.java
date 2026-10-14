package com.sdf.screens.student;

import com.sdf.Router;
import com.sdf.api.ApiException;
import com.sdf.api.AttemptService;
import com.sdf.api.Async;
import com.sdf.api.QuizService;
import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.*;
import javafx.util.Duration;

import java.time.Instant;
import java.time.LocalDateTime;
import java.time.OffsetDateTime;
import java.time.ZoneId;
import java.time.format.DateTimeFormatter;
import java.util.HashSet;
import java.util.LinkedHashMap;
import java.util.Map;
import java.util.Set;

/**
 * Live-data quiz attempt screen: pulls the quiz + questions from
 * GET /api/quizzes/{quiz} and submits via POST /api/attempts/{attempt}/submit.
 *
 * NOTE ON ATTEMPT ID: the API spec doesn't document a separate "start
 * attempt" endpoint, so this assumes GET /api/quizzes/{quiz} returns the
 * current user's attempt id embedded in the response (checked under a
 * few likely field names in QuizService). If that assumption is wrong
 * for your backend, this screen shows the raw JSON it received so you
 * can tell me the actual field name and I'll fix the one line that reads it.
 */
public class QuizAttemptScreen {

    public static Parent build(long quizId, String quizTitleFallback) {
        BorderPane root = new BorderPane();
        root.getStyleClass().add("bg-zinc-page");

        Label loading = new Label("Loading quiz\u2026");
        loading.setPadding(new Insets(40));
        loading.setStyle("-fx-font-size: 14px; -fx-text-fill: #71717a;");
        root.setCenter(loading);

            Async.run(
                    () -> QuizService.getDetail(quizId),
                    quiz -> {
                        if (quiz.blocked) {
                            VBox blockedBox = new VBox(12);
                            blockedBox.setPadding(new Insets(40));
                            blockedBox.setAlignment(Pos.CENTER);
                            Label msg = new Label(quiz.blockedMessage != null ? quiz.blockedMessage
                                    : "This quiz isn't available to attempt right now.");
                            msg.setStyle("-fx-text-fill:#3f3f46; -fx-font-size: 13px;");
                            msg.setWrapText(true);
                            Button back = new Button("Back to Quiz Center");
                            back.getStyleClass().add("btn-primary");
                            back.setOnAction(e -> Router.get().navigate(StudentQuizzesScreen::build));
                            blockedBox.getChildren().addAll(msg, back);
                            root.setCenter(blockedBox);
                            return;
                        }
                        renderQuiz(root, quiz);
                    },
                    err -> {
                    String msg = (err instanceof ApiException ae)
                            ? ae.firstMessage()
                            : "Couldn't reach the server. Check your connection and try again.";
                    VBox errBox = new VBox(12);
                    errBox.setPadding(new Insets(40));
                    errBox.setAlignment(Pos.CENTER);
                    Label errLabel = new Label("Couldn't load this quiz: " + msg);
                    errLabel.setStyle("-fx-text-fill:#dc2626; -fx-font-size: 13px;");
                    errLabel.setWrapText(true);
                    Button back = new Button("Back to Quiz Center");
                    back.getStyleClass().add("btn-primary");
                    back.setOnAction(e -> Router.get().navigate(StudentQuizzesScreen::build));
                    errBox.getChildren().addAll(errLabel, back);
                    root.setCenter(errBox);
                }
        );

        return root;
    }

    private static void renderQuiz(BorderPane root, QuizService.QuizDetail quiz) {
        if (quiz.questions.isEmpty()) {
            VBox emptyBox = new VBox(12);
            emptyBox.setPadding(new Insets(40));
            emptyBox.setAlignment(Pos.CENTER);
            Label l = new Label("This quiz has no questions to display yet.");
            l.setStyle("-fx-text-fill:#71717a; -fx-font-size: 13px;");
            Button back = new Button("Back to Quiz Center");
            back.getStyleClass().add("btn-primary");
            back.setOnAction(e -> Router.get().navigate(StudentQuizzesScreen::build));
            emptyBox.getChildren().addAll(l, back);
            root.setCenter(emptyBox);
            return;
        }

        Map<Long, String> selectedAnswers = new LinkedHashMap<>();
        Set<Long> markedForReview = new HashSet<>();
        int[] currentIndex = {0};
        boolean[] submitted = {false};

        // ---- header ----
        HBox header = new HBox();
        header.getStyleClass().add("quiz-header");
        header.setAlignment(Pos.CENTER_LEFT);
        header.setPadding(new Insets(14, 24, 14, 24));

        Button exit = new Button("\u2190  Exit");
        exit.setStyle("-fx-background-color:transparent; -fx-text-fill:white; -fx-font-weight:700; -fx-font-size:13px; -fx-cursor:hand;");

        Label examTitle = new Label(quiz.title.isBlank() ? "Quiz" : quiz.title);
        examTitle.setStyle("-fx-text-fill:white; -fx-font-size:16px; -fx-font-weight:800;");

        Region sp1 = new Region();
        HBox.setHgrow(sp1, Priority.ALWAYS);
        Region sp2 = new Region();
        HBox.setHgrow(sp2, Priority.ALWAYS);

        HBox timer = new HBox(8);
        timer.getStyleClass().add("quiz-timer-pill");
        timer.setAlignment(Pos.CENTER_LEFT);
        Label timerLabel = new Label("\u23F1  Time Remaining");
        timerLabel.setStyle("-fx-text-fill:#bbf7d0; -fx-font-size:12px; -fx-font-weight:600;");
        Label timerValue = new Label("--:--");
        timerValue.setStyle("-fx-text-fill:white; -fx-font-family:monospace; -fx-font-weight:800; -fx-font-size:13px;");
        timer.getChildren().addAll(timerLabel, timerValue);

        header.getChildren().addAll(exit, sp1, examTitle, sp2, timer);

        // ---- left: question nav ----
        VBox nav = new VBox(20);
        nav.setPrefWidth(260);
        nav.setStyle("-fx-background-color:white; -fx-border-color:transparent #e7e5e4 transparent transparent; -fx-border-width:0 1 0 0;");
        nav.setPadding(new Insets(20));

        Label qLabel = new Label("QUESTIONS");
        qLabel.setStyle("-fx-font-size: 11px; -fx-font-weight: 800; -fx-text-fill: #78716c;");

        GridPane grid = new GridPane();
        grid.setHgap(8);
        grid.setVgap(8);

        VBox legend = new VBox(8);
        legend.setStyle("-fx-background-color:#fafaf9; -fx-border-color:#e7e5e4; -fx-border-radius:10; -fx-background-radius:10; -fx-padding:14;");
        legend.getChildren().addAll(
                legendRow("#15803d", "Answered"),
                legendRow("#d97706", "Review"),
                legendRow("#d6d3d1", "Not Answered")
        );

        Button toggleReview = new Button("Mark for Review");
        toggleReview.getStyleClass().add("btn-outline");
        toggleReview.setMaxWidth(Double.MAX_VALUE);

        nav.getChildren().addAll(qLabel, grid, legend, toggleReview);

        // ---- center: question ----
        Label qOf = new Label();
        qOf.setStyle("-fx-text-fill:#15803d; -fx-font-weight:800; -fx-font-size:12px;");
        Label qText = new Label();
        qText.setWrapText(true);
        qText.setStyle("-fx-font-size: 20px; -fx-font-weight: 600; -fx-text-fill: #1c1917;");

        VBox options = new VBox(12);

        HBox navButtons = new HBox();
        navButtons.setAlignment(Pos.CENTER_LEFT);
        Button prev = new Button("\u2190  Previous");
        prev.getStyleClass().add("btn-outline");
        Region sp3 = new Region();
        HBox.setHgrow(sp3, Priority.ALWAYS);
        Button next = new Button("Next  \u2192");
        next.getStyleClass().add("btn-primary");
        navButtons.getChildren().addAll(prev, sp3, next);

        Button submit = new Button("\u2691  Submit Quiz");
        submit.setStyle("-fx-background-color:#166534; -fx-text-fill:white; -fx-font-weight:800; -fx-background-radius:10; -fx-padding:12 28 12 28;");
        HBox submitRow = new HBox(submit);
        submitRow.setAlignment(Pos.CENTER_RIGHT);

        VBox question = new VBox(24, qOf, qText, options, navButtons, submitRow);
        question.setPadding(new Insets(40, 60, 40, 60));
        question.setMaxWidth(760);

        HBox center = new HBox(question);
        center.setAlignment(Pos.TOP_CENTER);
        ScrollPane centerScroll = new ScrollPane(center);
        centerScroll.setFitToWidth(true);
        centerScroll.getStyleClass().add("scroll-pane");

        HBox body = new HBox(nav, centerScroll);
        HBox.setHgrow(centerScroll, Priority.ALWAYS);

        root.setTop(header);
        root.setCenter(body);

        // ---- rendering logic ----
        Runnable[] refreshNav = new Runnable[1];
        Runnable[] renderQuestion = new Runnable[1];

        refreshNav[0] = () -> {
            grid.getChildren().clear();
            for (int i = 0; i < quiz.questions.size(); i++) {
                long qId = quiz.questions.get(i).id;
                Button b = new Button(String.valueOf(i + 1));
                b.setPrefSize(48, 40);
                if (markedForReview.contains(qId)) {
                    b.getStyleClass().add("qnum-review");
                } else if (selectedAnswers.containsKey(qId)) {
                    b.getStyleClass().add("qnum-answered");
                } else {
                    b.getStyleClass().add("qnum-unanswered");
                }
                if (i == currentIndex[0]) {
                    b.setStyle("-fx-border-color:#166534; -fx-border-width:2; -fx-border-radius:8;");
                }
                int target = i;
                b.setOnAction(e -> {
                    currentIndex[0] = target;
                    renderQuestion[0].run();
                });
                grid.add(b, i % 4, i / 4);
            }
        };

        renderQuestion[0] = () -> {
            int idx = currentIndex[0];
            QuizService.QuestionDetail q = quiz.questions.get(idx);
            qOf.setText("QUESTION " + (idx + 1) + " OF " + quiz.questions.size());
            qText.setText(q.text);

            options.getChildren().clear();
            ToggleGroup tg = new ToggleGroup();
            for (Map.Entry<String, String> opt : q.options.entrySet()) {
                String letter = opt.getKey();
                boolean selected = letter.equals(selectedAnswers.get(q.id));
                RadioButton rb = new RadioButton();
                rb.setToggleGroup(tg);
                rb.setSelected(selected);
                Label l = new Label(letter + ". " + opt.getValue());
                l.setWrapText(true);
                l.setStyle("-fx-font-size: 15px; -fx-text-fill: " + (selected ? "#1c1917; -fx-font-weight:600;" : "#57534e;"));
                HBox row = new HBox(14, rb, l);
                row.setAlignment(Pos.CENTER_LEFT);
                row.getStyleClass().add(selected ? "quiz-option-selected" : "quiz-option");
                row.setOnMouseClicked(e -> {
                    selectedAnswers.put(q.id, letter);
                    renderQuestion[0].run();
                    refreshNav[0].run();
                });
                options.getChildren().add(row);
            }

            toggleReview.setText(markedForReview.contains(q.id) ? "Unmark Review" : "Mark for Review");
            toggleReview.setOnAction(e -> {
                if (markedForReview.contains(q.id)) markedForReview.remove(q.id);
                else markedForReview.add(q.id);
                refreshNav[0].run();
                renderQuestion[0].run();
            });

            prev.setDisable(idx == 0);
            prev.setOnAction(e -> {
                currentIndex[0] = Math.max(0, idx - 1);
                renderQuestion[0].run();
                refreshNav[0].run();
            });
            next.setDisable(idx == quiz.questions.size() - 1);
            next.setOnAction(e -> {
                currentIndex[0] = Math.min(quiz.questions.size() - 1, idx + 1);
                renderQuestion[0].run();
                refreshNav[0].run();
            });

            refreshNav[0].run();
        };

        Runnable[] doSubmit = new Runnable[1];
        doSubmit[0] = () -> {
            if (submitted[0]) return;
            submitted[0] = true;
            submit.setDisable(true);
            submit.setText("Submitting\u2026");

            if (quiz.attemptId == null) {
                new Alert(Alert.AlertType.ERROR,
                        "Couldn't submit: the server's quiz response didn't include an attempt id in any of the "
                                + "field names this app checks for. Raw response:\n\n" + quiz.rawJson).showAndWait();
                submitted[0] = false;
                submit.setDisable(false);
                submit.setText("\u2691  Submit Quiz");
                return;
            }

            Async.run(
                    () -> AttemptService.submit(quiz.attemptId, selectedAnswers, false),
                    result -> {
                        String msg = result.scoreText != null ? "Score: " + result.scoreText
                                : (result.message != null ? result.message : "Your quiz has been submitted.");
                        new Alert(Alert.AlertType.INFORMATION, msg).showAndWait();
                        Router.get().navigate(StudentQuizzesScreen::build);
                    },
                    err -> {
                        submitted[0] = false;
                        submit.setDisable(false);
                        submit.setText("\u2691  Submit Quiz");
                        String msg = (err instanceof ApiException ae) ? ae.firstMessage()
                                : "Couldn't reach the server. Check your connection and try again.";
                        new Alert(Alert.AlertType.ERROR, "Couldn't submit: " + msg).showAndWait();
                    }
            );
        };

        submit.setOnAction(e -> {
            Alert confirm = new Alert(Alert.AlertType.CONFIRMATION,
                    "Submit your answers now? This can't be undone.", ButtonType.YES, ButtonType.CANCEL);
            confirm.showAndWait().ifPresent(bt -> {
                if (bt == ButtonType.YES) doSubmit[0].run();
            });
        });

        exit.setOnAction(e -> {
            Alert confirm = new Alert(Alert.AlertType.CONFIRMATION,
                    "Leaving now will submit your answers as they are. Continue?", ButtonType.YES, ButtonType.CANCEL);
            confirm.showAndWait().ifPresent(bt -> {
                if (bt == ButtonType.YES) {
                    if (quiz.attemptId != null && !submitted[0]) {
                        submitted[0] = true;
                        Async.run(
                                () -> AttemptService.submit(quiz.attemptId, selectedAnswers, true),
                                result -> Router.get().navigate(StudentQuizzesScreen::build),
                                err -> Router.get().navigate(StudentQuizzesScreen::build)
                        );
                    } else {
                        Router.get().navigate(StudentQuizzesScreen::build);
                    }
                }
            });
        });

        renderQuestion[0].run();

        // ---- countdown ----
        Long totalSeconds = computeRemainingSeconds(quiz);
        if (totalSeconds != null) {
            long[] remaining = {totalSeconds};
            timerValue.setText(formatCountdown(remaining[0]));
            Timeline countdown = new Timeline();
            countdown.setCycleCount(Timeline.INDEFINITE);
            countdown.getKeyFrames().add(new KeyFrame(Duration.seconds(1), e -> {
                if (root.getScene() == null) {
                    countdown.stop();
                    return;
                }
                remaining[0]--;
                if (remaining[0] <= 0) {
                    timerValue.setText("00:00");
                    countdown.stop();
                    if (!submitted[0]) doSubmit[0].run();
                    return;
                }
                timerValue.setText(formatCountdown(remaining[0]));
            }));
            countdown.play();
        } else {
            timerValue.setText("N/A");
        }
    }

    /**
     * Prefers ends_at - server_time from the quiz response (so it doesn't
     * matter whether this machine's clock matches the server's). Falls back
     * to the quiz's time_limit in minutes, counted from now, if the server
     * didn't send parseable timestamps.
     */
    private static Long computeRemainingSeconds(QuizService.QuizDetail quiz) {
        Instant serverTime = parseFlexibleInstant(quiz.serverTime);
        Instant endsAt = parseFlexibleInstant(quiz.endsAt);
        if (serverTime != null && endsAt != null) {
            long secs = endsAt.getEpochSecond() - serverTime.getEpochSecond();
            if (secs > 0) return secs;
        }
        if (quiz.timeLimitMinutes != null && quiz.timeLimitMinutes > 0) {
            return quiz.timeLimitMinutes * 60L;
        }
        return null;
    }

    private static Instant parseFlexibleInstant(String s) {
        if (s == null || s.isBlank()) return null;
        try {
            return Instant.parse(s);
        } catch (Exception ignored) { }
        try {
            return OffsetDateTime.parse(s).toInstant();
        } catch (Exception ignored) { }
        try {
            return LocalDateTime.parse(s.replace(" ", "T"), DateTimeFormatter.ISO_LOCAL_DATE_TIME)
                    .atZone(ZoneId.systemDefault()).toInstant();
        } catch (Exception ignored) { }
        return null;
    }

    private static String formatCountdown(long totalSeconds) {
        long m = totalSeconds / 60;
        long s = totalSeconds % 60;
        return String.format("%02d:%02d", m, s);
    }

    private static HBox legendRow(String color, String text) {
        Region swatch = new Region();
        swatch.setStyle("-fx-background-color:" + color + "; -fx-background-radius:4;");
        swatch.setPrefSize(14, 14);
        Label l = new Label(text.toUpperCase());
        l.setStyle("-fx-font-size: 10px; -fx-font-weight: 700; -fx-text-fill: #57534e;");
        HBox row = new HBox(10, swatch, l);
        row.setAlignment(Pos.CENTER_LEFT);
        return row;
    }
}
