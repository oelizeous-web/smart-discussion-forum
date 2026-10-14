package com.sdf.screens.student;

import com.sdf.Router;
import com.sdf.api.ApiException;
import com.sdf.api.Async;
import com.sdf.api.NotificationService;
import com.sdf.components.Sidebar;
import com.sdf.components.UiKit;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.control.Label;
import javafx.scene.layout.*;
import javafx.scene.control.ScrollPane;

import java.util.LinkedHashMap;

public class NotificationsScreen {

    public static Parent build() {
        BorderPane root = new BorderPane();
        root.getStyleClass().add("app-shell");

        LinkedHashMap<String, Runnable> links = new LinkedHashMap<>();
        links.put("Dashboard", () -> Router.get().navigate(StudentDashboardScreen::build));
        links.put("Discussions", () -> Router.get().navigate(StudentDiscussionsScreen::build));
        links.put("Quizzes", () -> Router.get().navigate(StudentQuizzesScreen::build));
        links.put("Notifications", null);
        root.setLeft(new Sidebar("Notifications", com.sdf.session.Session.get().getInitial(), com.sdf.session.Session.get().getName(), links));

        VBox content = new VBox(18);
        content.getStyleClass().add("bg-cream");
        content.setPadding(new Insets(28));

        Label title = new Label("Notifications");
        title.setStyle("-fx-font-size: 22px; -fx-font-weight: 800; -fx-text-fill: #18181b;");

        VBox list = new VBox(10);
        Label loading = new Label("Loading\u2026");
        loading.setStyle("-fx-text-fill: #a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");
        list.getChildren().add(loading);

        content.getChildren().addAll(title, list);

        ScrollPane scroll = new ScrollPane(content);
        scroll.setFitToWidth(true);
        scroll.getStyleClass().add("scroll-pane");
        root.setCenter(scroll);

        Async.run(
                NotificationService::list,
                notifs -> {
                    list.getChildren().clear();
                    if (notifs.isEmpty()) {
                        Label empty = new Label("No notifications yet.");
                        empty.setStyle("-fx-text-fill: #a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");
                        list.getChildren().add(empty);
                        return;
                    }
                    for (NotificationService.Notif n : notifs) {
                        list.getChildren().add(notifRow(n));
                    }
                },
                err -> {
                    list.getChildren().clear();
                    Label errLabel = new Label("Couldn't load notifications: " + friendlyMessage(err));
                    errLabel.setStyle("-fx-text-fill: #dc2626; -fx-font-size: 12px;");
                    list.getChildren().add(errLabel);
                }
        );

        return root;
    }

    private static VBox notifRow(NotificationService.Notif n) {
        VBox row = UiKit.card();
        row.setStyle(row.getStyle() + (n.read ? "" : "-fx-border-color: #2F7A54; -fx-border-width: 1.5;"));
        Label msg = new Label(n.message);
        msg.setWrapText(true);
        msg.setStyle(n.read ? "-fx-text-fill: #71717a; -fx-font-size: 13px;"
                             : "-fx-font-weight: 700; -fx-text-fill: #18181b; -fx-font-size: 13px;");
        row.getChildren().add(msg);
        row.setStyle(row.getStyle() + "-fx-cursor: hand;");
        row.setOnMouseClicked(e -> {
            Async.run(
                    () -> { if (!n.read) NotificationService.markRead(n.id); return null; },
                    v -> Router.get().navigate(() ->
                            StudentDiscussionsScreen.build(n.groupId, null, n.topicId, n.postId)),
                    err -> new javafx.scene.control.Alert(javafx.scene.control.Alert.AlertType.ERROR,
                            "Couldn't open: " + friendlyMessage(err)).showAndWait()
            );
        });
        return row;
    }

    private static String friendlyMessage(Exception err) {
        if (err instanceof ApiException ae) return ae.firstMessage();
        return "Couldn't reach the server. Check your connection and try again.";
    }
}