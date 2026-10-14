package com.sdf.screens.student;

import com.sdf.Router;
import com.sdf.api.ApiException;
import com.sdf.api.Async;
import com.sdf.api.GroupService;
import com.sdf.api.LivePoller;
import com.sdf.api.NotificationService;
import com.sdf.api.QuizService;
import com.sdf.components.JoinGroupDialog;
import com.sdf.components.Sidebar;
import com.sdf.components.UiKit;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.Parent;
import javafx.scene.layout.*;
import javafx.util.Duration;

import java.time.Instant;
import java.util.LinkedHashMap;
import java.util.List;

public class StudentDashboardScreen {

    public static Parent build() {
        BorderPane root = new BorderPane();
        root.getStyleClass().add("app-shell");

        LinkedHashMap<String, Runnable> links = new LinkedHashMap<>();
        links.put("Dashboard", null);
        links.put("Discussions", () -> Router.get().navigate(StudentDiscussionsScreen::build));
        links.put("Quizzes", () -> Router.get().navigate(StudentQuizzesScreen::build));
        links.put("Notifications", () -> Router.get().navigate(NotificationsScreen::build));
        Sidebar sidebar = new Sidebar("Dashboard", com.sdf.session.Session.get().getInitial(), com.sdf.session.Session.get().getName(), links);
        root.setLeft(sidebar);

        VBox content = new VBox(24);
        content.getStyleClass().add("bg-cream");
        content.setPadding(new Insets(28));

        HBox headerRow = new HBox();
        headerRow.setAlignment(Pos.CENTER_LEFT);
        Label title = new Label("Dashboard Overview");
        title.setStyle("-fx-font-size: 22px; -fx-font-weight: 800; -fx-text-fill: #18181b;");
        Region sp = new Region();
        HBox.setHgrow(sp, Priority.ALWAYS);
        Button joinBtn = UiKit.primaryButton("+  Join a Group");
        headerRow.getChildren().addAll(title, sp, joinBtn);

        // Stat cards — populated for real once the first load completes.
        Label myGroupsStat = new Label("\u2026");
        Label toJoinStat = new Label("\u2026");
        Label pendingQuizzesStat = new Label("\u2026");
        Label avgScoreStat = new Label("\u2026");
        Label unreadMsgsStat = new Label("\u2026");
        VBox myGroupsCard = statCardWithLabel("My Groups", myGroupsStat, null);
        VBox toJoinCard = statCardWithLabel("Groups You Can Join", toJoinStat, null);
        VBox pendingQuizzesCard = statCardWithLabel("Pending Quizzes", pendingQuizzesStat,
                "-fx-text-fill:#ca8a04; -fx-font-size:28px; -fx-font-weight:800;");
        VBox avgScoreCard = statCardWithLabel("Avg. Score", avgScoreStat,
                "-fx-text-fill:#2F7A54; -fx-font-size:28px; -fx-font-weight:800;");
        VBox unreadMsgsCard = statCardWithLabel("Unread Msgs (7 Days)", unreadMsgsStat, null);
        HBox stats = new HBox(18, myGroupsCard, toJoinCard, pendingQuizzesCard, avgScoreCard, unreadMsgsCard);
        for (var n : stats.getChildren()) HBox.setHgrow(n, Priority.ALWAYS);

        // groups column
        VBox groupsBox = new VBox(12);
        Label groupsTitle = new Label("MY GROUPS");
        groupsTitle.setStyle("-fx-font-size: 14px; -fx-font-weight: 800; -fx-text-fill: #18181b;");
        groupsBox.getChildren().add(groupsTitle);
        VBox myGroupsList = new VBox(10);
        groupsBox.getChildren().add(myGroupsList);

        VBox availableBox = UiKit.card();
        availableBox.setPrefWidth(340);
        Label availableTitle = new Label("GROUPS YOU CAN JOIN");
        availableTitle.setStyle("-fx-font-size: 12px; -fx-font-weight: 800; -fx-text-fill: #18181b;");
        availableBox.getChildren().add(availableTitle);
        VBox availableList = new VBox(8);
        availableBox.getChildren().add(availableList);

        HBox middle = new HBox(20, groupsBox, availableBox);
        HBox.setHgrow(groupsBox, Priority.ALWAYS);

        Label statusLabel = new Label("Loading your groups\u2026");
        statusLabel.setStyle("-fx-text-fill: #a1a1aa; -fx-font-size: 12px; -fx-font-style: italic;");

        content.getChildren().addAll(headerRow, stats, statusLabel, middle);

        ScrollPane scroll = new ScrollPane(content);
        scroll.setFitToWidth(true);
        scroll.getStyleClass().add("scroll-pane");
        root.setCenter(scroll);

        Runnable[] refreshHolder = new Runnable[1];
        refreshHolder[0] = () -> {
            Async.run(
                    GroupService::list,
                    result -> {
                        statusLabel.setVisible(false);
                        statusLabel.setManaged(false);
                        myGroupsStat.setText(String.valueOf(result.myGroups.size()));
                        toJoinStat.setText(String.valueOf(result.availableGroups.size()));

                        myGroupsList.getChildren().clear();
                        if (result.myGroups.isEmpty()) {
                            Label empty = new Label("You haven't joined any groups yet.");
                            empty.setStyle("-fx-text-fill: #a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");
                            myGroupsList.getChildren().add(empty);
                        } else {
                            for (GroupService.Group g : result.myGroups) {
                                myGroupsList.getChildren().add(groupRow(g));
                            }
                        }

                        availableList.getChildren().clear();
                        if (result.availableGroups.isEmpty()) {
                            Label empty = new Label("No new groups to join right now.");
                            empty.setStyle("-fx-text-fill: #a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");
                            availableList.getChildren().add(empty);
                        } else {
                            for (GroupService.Group g : result.availableGroups) {
                                availableList.getChildren().add(availableGroupRow(g));
                            }
                        }
                    },
                    err -> {
                        statusLabel.setText("Couldn't load your groups: " + friendlyMessage(err));
                        statusLabel.setStyle("-fx-text-fill: #dc2626; -fx-font-size: 12px;");
                    }
            );

            Async.run(
                    QuizService::list,
                    quizzes -> {
                        long pending = quizzes.stream()
                                .filter(q -> !q.attempted && hasStarted(q.startTime))
                                .count();
                        pendingQuizzesStat.setText(String.valueOf(pending));

                        List<Integer> numericScores = quizzes.stream()
                                .filter(q -> q.attempted)
                                .map(q -> q.scoreText)
                                .filter(s -> s != null && s.replace("%", "").matches("\\d+"))
                                .map(s -> Integer.parseInt(s.replace("%", "")))
                                .toList();
                        avgScoreStat.setText(numericScores.isEmpty() ? "\u2014"
                                : (int) numericScores.stream().mapToInt(Integer::intValue).average().orElse(0) + "%");
                    },
                    err -> {
                        pendingQuizzesStat.setText("\u2014");
                        avgScoreStat.setText("\u2014");
                    }
            );

            Async.run(
                    () -> NotificationService.unreadCountWithinDays(7),
                    count -> unreadMsgsStat.setText(String.valueOf(count)),
                    err -> unreadMsgsStat.setText("\u2014")
            );
        };

        refreshHolder[0].run();
        // Keep the dashboard current without the user having to reload the screen —
        // mirrors "someone joins/creates a group on the web, it shows up here too".
        LivePoller.start(root, Duration.seconds(6), refreshHolder[0]);

        joinBtn.setOnAction(e -> JoinGroupDialog.show(refreshHolder[0]));

        return root;
    }

    /** A quiz "counts" as pending once its scheduled start has already passed and the student hasn't attempted it. */
    private static boolean hasStarted(String startTime) {
        if (startTime == null || startTime.isBlank()) return true;
        try {
            return !Instant.parse(startTime).isAfter(Instant.now());
        } catch (Exception e) {
            return true;
        }
    }

    private static VBox statCardWithLabel(String label, Label valueLabel, String valueColorStyle) {
        VBox box = new VBox(10);
        box.getStyleClass().add("stat-card");
        Label l = new Label(label.toUpperCase());
        l.getStyleClass().add("stat-label");
        valueLabel.getStyleClass().add("stat-value");
        if (valueColorStyle != null) valueLabel.setStyle(valueColorStyle);
        box.getChildren().addAll(l, valueLabel);
        HBox.setHgrow(box, Priority.ALWAYS);
        return box;
    }

    private static HBox groupRow(GroupService.Group g) {
        HBox row = new HBox();
        row.getStyleClass().add("card");
        row.setAlignment(Pos.CENTER_LEFT);
        row.setStyle("-fx-cursor: hand;");
        VBox text = new VBox(4);
        Label n = new Label(g.name);
        n.setStyle("-fx-font-weight: 800; -fx-text-fill: #18181b;");
        String topicSummary = g.topics.isEmpty()
                ? "Group ID: " + g.id + " \u00b7 no topics yet"
                : "Group ID: " + g.id + " \u00b7 " + g.topics.size() + " topic(s)";
        Label s = new Label(topicSummary);
        s.setStyle("-fx-text-fill: #71717a; -fx-font-size: 12px;");
        text.getChildren().addAll(n, s);
        row.getChildren().add(text);
        row.setOnMouseClicked(e -> {
            long firstTopicId = g.topics.isEmpty() ? -1 : g.topics.get(0).id;
            Router.get().navigate(() -> StudentDiscussionsScreen.build(g.id, g.name, firstTopicId));
        });
        return row;
    }

    private static HBox availableGroupRow(GroupService.Group g) {
        HBox row = new HBox(10);
        row.setAlignment(Pos.CENTER_LEFT);
        row.setStyle("-fx-background-color:#fafafa; -fx-border-color:#e4e4e7; -fx-border-radius:10; -fx-background-radius:10; -fx-padding:12;");
        VBox text = new VBox(2);
        Label n = new Label(g.name);
        n.setStyle("-fx-font-weight: 700; -fx-text-fill: #18181b; -fx-font-size: 13px;");
        Label s = new Label("Group ID: " + g.id);
        s.setStyle("-fx-text-fill: #71717a; -fx-font-size: 11px;");
        text.getChildren().addAll(n, s);
        Region sp = new Region();
        HBox.setHgrow(sp, Priority.ALWAYS);
        Button joinOne = new Button("Join");
        joinOne.getStyleClass().add("btn-outline");
        joinOne.setOnAction(e -> {
            joinOne.setDisable(true);
            joinOne.setText("Joining\u2026");
            Async.run(
                    () -> { GroupService.join(g.id); return null; },
                    v -> Router.get().navigate(StudentDashboardScreen::build), // simplest reliable refresh
                    err -> {
                        joinOne.setDisable(false);
                        joinOne.setText("Join");
                        new Alert(Alert.AlertType.ERROR, friendlyMessage(err)).showAndWait();
                    }
            );
        });
        row.getChildren().addAll(text, sp, joinOne);
        return row;
    }

    private static String friendlyMessage(Exception err) {
        if (err instanceof ApiException ae) return ae.firstMessage();
        return "Couldn't reach the server. Check your connection and try again.";
    }
}