package com.sdf.screens.admin;

import com.fasterxml.jackson.databind.JsonNode;
import com.sdf.Router;
import com.sdf.api.AdminService;
import com.sdf.api.ApiException;
import com.sdf.api.Async;
import com.sdf.components.Sidebar;
import com.sdf.components.UiKit;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.BorderPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;

import java.util.LinkedHashMap;

/**
 * Backed by:
 *  - GET  /api/admin/lecturers            (Lecturers tab)
 *  - GET  /api/admin/users                (Members tab)
 *  - POST /api/admin/users/{id}/warn      (Members tab)
 *  - POST /api/admin/users/{id}/suspend   (Members tab)
 *  - POST /api/admin/users/{id}/reinstate (Members tab)
 *  - GET  /api/admin/flags                (Moderation tab)
 *  - POST /api/admin/flags/{id}/resolve   (Moderation tab)
 */
public class AdminMembersScreen {

    public static Parent build() {
        BorderPane root = new BorderPane();
        root.getStyleClass().add("app-shell");

        LinkedHashMap<String, Runnable> links = new LinkedHashMap<>();
        links.put("Dashboard", () -> Router.get().navigate(AdminDashboardScreen::build));
        links.put("Members", null);
        links.put("Settings", () -> Router.get().navigate(AdminSettingsScreen::build));
        links.put("Reports", () -> Router.get().navigate(AdminReportsScreen::build));
        root.setLeft(new Sidebar("Members", com.sdf.session.Session.get().getInitial(), com.sdf.session.Session.get().getName(), links));

        VBox content = new VBox(18);
        content.getStyleClass().add("bg-gray-page");
        content.setPadding(new Insets(28));

        Label title = new Label("User Management");
        title.setStyle("-fx-font-size: 22px; -fx-font-weight: 800; -fx-text-fill: #18181b;");
        Label sub = new Label("Lecturers, members, and flagged content \u2014 live from the server.");
        sub.getStyleClass().add("muted");

        TabPane tabs = new TabPane();
        tabs.setTabClosingPolicy(TabPane.TabClosingPolicy.UNAVAILABLE);
        Tab lecturersTab = new Tab("Lecturers", buildLecturersPane());
        Tab membersTab = new Tab("Members", buildMembersPane());
        Tab moderationTab = new Tab("Moderation", buildModerationPane());
        tabs.getTabs().addAll(lecturersTab, membersTab, moderationTab);

        content.getChildren().addAll(title, sub, tabs);
        VBox.setVgrow(tabs, Priority.ALWAYS);
        root.setCenter(content);

        return root;
    }

    // ---- Lecturers tab -----------------------------------------------

    private static VBox buildLecturersPane() {
        VBox pane = new VBox(14);
        pane.setPadding(new Insets(16, 0, 0, 0));

        Label heading = new Label("Faculty Members");
        heading.setStyle("-fx-font-size: 16px; -fx-font-weight: 800; -fx-text-fill: #18181b;");
        Button addBtn = UiKit.primaryButton("+ Add Lecturer");
        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);
        HBox headerRow = new HBox(heading, spacer, addBtn);
        headerRow.setAlignment(Pos.CENTER_LEFT);

        VBox list = new VBox(10);
        Label loading = new Label("Loading lecturers\u2026");
        loading.setStyle("-fx-text-fill:#a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");
        list.getChildren().add(loading);

        Runnable[] refresh = new Runnable[1];
        refresh[0] = () -> {
            Async.run(
                    AdminService::listLecturers,
                    json -> {
                        list.getChildren().clear();
                        JsonNode arr = json.has("data") ? json.get("data") : json;
                        if (arr == null || !arr.isArray() || arr.isEmpty()) {
                            Label empty = new Label("No lecturers yet.");
                            empty.setStyle("-fx-text-fill:#a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");
                            list.getChildren().add(empty);
                            return;
                        }
                        for (JsonNode l : arr) {
                            list.getChildren().add(lecturerRow(
                                    l.path("name").asText("Unknown"),
                                    l.path("email").asText("")
                            ));
                        }
                    },
                    err -> {
                        list.getChildren().clear();
                        Label errLabel = new Label("Couldn't load lecturers: " + friendlyMessage(err));
                        errLabel.setStyle("-fx-text-fill:#dc2626; -fx-font-size: 12px;");
                        list.getChildren().add(errLabel);
                    }
            );
        };
        refresh[0].run();

        addBtn.setOnAction(e -> showAddLecturerDialog(refresh[0]));

        pane.getChildren().addAll(headerRow, list);
        return pane;
    }

    private static HBox lecturerRow(String name, String email) {
        HBox row = new HBox(10);
        row.setAlignment(Pos.CENTER_LEFT);
        row.setStyle("-fx-background-color:white; -fx-border-color:#e4e4e7; -fx-border-radius:10; -fx-background-radius:10; -fx-padding:14;");
        VBox text = new VBox(2);
        Label n = new Label(name);
        n.setStyle("-fx-font-weight: 800; -fx-font-size: 13px; -fx-text-fill: #18181b;");
        Label em = new Label(email);
        em.setStyle("-fx-font-size: 11px; -fx-text-fill: #71717a;");
        text.getChildren().addAll(n, em);
        row.getChildren().add(text);
        return row;
    }

    private static void showAddLecturerDialog(Runnable onCreated) {
        Dialog<Void> dialog = new Dialog<>();
        dialog.setTitle("Add Lecturer");
        dialog.getDialogPane().getButtonTypes().addAll(ButtonType.CANCEL);

        TextField name = new TextField();
        name.setPromptText("Full name");
        TextField email = new TextField();
        email.setPromptText("Email");
        PasswordField password = new PasswordField();
        password.setPromptText("Password (min 8 characters)");
        PasswordField confirm = new PasswordField();
        confirm.setPromptText("Confirm password");
        Label error = new Label();
        error.setStyle("-fx-text-fill:#dc2626; -fx-font-size: 11px;");
        error.setWrapText(true);
        error.setManaged(false);
        error.setVisible(false);

        Button createBtn = new Button("Register Lecturer");
        createBtn.getStyleClass().add("btn-primary");
        createBtn.setOnAction(e -> {
            error.setManaged(false);
            error.setVisible(false);
            if (name.getText().isBlank() || email.getText().isBlank() || password.getText().isBlank()) {
                error.setText("Fill in every field.");
                error.setManaged(true);
                error.setVisible(true);
                return;
            }
            if (!password.getText().equals(confirm.getText())) {
                error.setText("Passwords don't match.");
                error.setManaged(true);
                error.setVisible(true);
                return;
            }
            createBtn.setDisable(true);
            String nameVal = name.getText().trim();
            String emailVal = email.getText().trim();
            Async.run(
                    () -> { AdminService.registerLecturer(nameVal, emailVal, password.getText(), confirm.getText()); return null; },
                    v -> {
                        dialog.close();
                        onCreated.run();
                    },
                    err -> {
                        createBtn.setDisable(false);
                        error.setText(friendlyMessage(err));
                        error.setManaged(true);
                        error.setVisible(true);
                    }
            );
        });

        VBox form = new VBox(10, name, email, password, confirm, error, createBtn);
        form.setPadding(new Insets(16));
        form.setPrefWidth(320);
        dialog.getDialogPane().setContent(form);
        dialog.showAndWait();
    }

    // ---- Members tab ---------------------------------------------------

    private static VBox buildMembersPane() {
        VBox pane = new VBox(14);
        pane.setPadding(new Insets(16, 0, 0, 0));

        Label heading = new Label("All Members");
        heading.setStyle("-fx-font-size: 16px; -fx-font-weight: 800; -fx-text-fill: #18181b;");

        VBox list = new VBox(10);
        Label loading = new Label("Loading members\u2026");
        loading.setStyle("-fx-text-fill:#a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");
        list.getChildren().add(loading);

        Runnable[] refresh = new Runnable[1];
        refresh[0] = () -> {
            Async.run(
                    AdminService::listUsers,
                    json -> {
                        list.getChildren().clear();
                        JsonNode arr = json.has("data") ? json.get("data") : json;
                        if (arr == null || !arr.isArray() || arr.isEmpty()) {
                            Label empty = new Label("No members yet.");
                            empty.setStyle("-fx-text-fill:#a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");
                            list.getChildren().add(empty);
                            return;
                        }
                        for (JsonNode u : arr) {
                            list.getChildren().add(memberRow(u, refresh[0]));
                        }
                    },
                    err -> {
                        list.getChildren().clear();
                        Label errLabel = new Label("Couldn't load members: " + friendlyMessage(err));
                        errLabel.setStyle("-fx-text-fill:#dc2626; -fx-font-size: 12px;");
                        list.getChildren().add(errLabel);
                    }
            );
        };
        refresh[0].run();

        pane.getChildren().addAll(heading, list);
        return pane;
    }

    private static HBox memberRow(JsonNode u, Runnable onChanged) {
        long id = u.path("id").asLong();
        String name = u.path("name").asText("Unknown");
        String email = u.path("email").asText("");
        String status = u.path("status").asText("ACTIVE");
        int warnings = u.path("warning_count").asInt(0);

        StringBuilder roles = new StringBuilder();
        JsonNode rolesArr = u.path("roles");
        if (rolesArr.isArray()) {
            for (JsonNode r : rolesArr) {
                if (roles.length() > 0) roles.append(", ");
                roles.append(r.asText());
            }
        }

        HBox row = new HBox(14);
        row.setAlignment(Pos.CENTER_LEFT);
        row.setStyle("-fx-background-color:white; -fx-border-color:#e4e4e7; -fx-border-radius:10; -fx-background-radius:10; -fx-padding:14;");

        VBox text = new VBox(2);
        Label n = new Label(name + "  \u00b7  " + roles);
        n.setStyle("-fx-font-weight: 800; -fx-font-size: 13px; -fx-text-fill: #18181b;");
        Label em = new Label(email + "   \u2022 status: " + status + "   \u2022 warnings: " + warnings);
        em.setStyle("-fx-font-size: 11px; -fx-text-fill: #71717a;");
        text.getChildren().addAll(n, em);
        HBox.setHgrow(text, Priority.ALWAYS);

        Button warnBtn = UiKit.outlineButton("Warn");
        Button suspendBtn = UiKit.dangerButton("SUSPENDED".equals(status) ? "Reinstate" : "Suspend");

        warnBtn.setOnAction(e -> {
            warnBtn.setDisable(true);
            Async.run(
                    () -> { AdminService.warnUser(id); return null; },
                    v -> onChanged.run(),
                    err -> warnBtn.setDisable(false)
            );
        });

        suspendBtn.setOnAction(e -> {
            suspendBtn.setDisable(true);
            boolean isSuspended = "SUSPENDED".equals(status);
            Async.run(
                    () -> {
                        if (isSuspended) AdminService.reinstateUser(id);
                        else AdminService.suspendUser(id);
                        return null;
                    },
                    v -> onChanged.run(),
                    err -> suspendBtn.setDisable(false)
            );
        });

        row.getChildren().addAll(text, warnBtn, suspendBtn);
        return row;
    }

    // ---- Moderation tab --------------------------------------------------

    private static VBox buildModerationPane() {
        VBox pane = new VBox(14);
        pane.setPadding(new Insets(16, 0, 0, 0));

        Label heading = new Label("Flagged Posts");
        heading.setStyle("-fx-font-size: 16px; -fx-font-weight: 800; -fx-text-fill: #18181b;");

        VBox list = new VBox(10);
        Label loading = new Label("Loading flagged posts\u2026");
        loading.setStyle("-fx-text-fill:#a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");
        list.getChildren().add(loading);

        Runnable[] refresh = new Runnable[1];
        refresh[0] = () -> {
            Async.run(
                    AdminService::listFlags,
                    json -> {
                        list.getChildren().clear();
                        JsonNode arr = json.has("data") ? json.get("data") : json;
                        if (arr == null || !arr.isArray() || arr.isEmpty()) {
                            Label empty = new Label("Nothing pending review.");
                            empty.setStyle("-fx-text-fill:#a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");
                            list.getChildren().add(empty);
                            return;
                        }
                        for (JsonNode f : arr) {
                            list.getChildren().add(flagRow(f, refresh[0]));
                        }
                    },
                    err -> {
                        list.getChildren().clear();
                        Label errLabel = new Label("Couldn't load flagged posts: " + friendlyMessage(err));
                        errLabel.setStyle("-fx-text-fill:#dc2626; -fx-font-size: 12px;");
                        list.getChildren().add(errLabel);
                    }
            );
        };
        refresh[0].run();

        pane.getChildren().addAll(heading, list);
        return pane;
    }

    private static VBox flagRow(JsonNode f, Runnable onChanged) {
        long flagId = f.path("id").asLong();
        String postContent = f.path("post_content").asText("");
        String postAuthor = f.path("post_author").asText("Unknown");
        String reporter = f.path("reporter_name").asText("Unknown");
        String reason = f.path("reason").asText("");
        String topicTitle = f.path("topic_title").asText("");

        VBox card = new VBox(8);
        card.setStyle("-fx-background-color:white; -fx-border-color:#e4e4e7; -fx-border-radius:10; -fx-background-radius:10; -fx-padding:14;");

        Label content = new Label("\"" + postContent + "\"");
        content.setWrapText(true);
        content.setStyle("-fx-font-size: 13px; -fx-text-fill: #18181b; -fx-font-weight: 700;");

        Label meta = new Label("By " + postAuthor + " in \"" + topicTitle + "\"  \u2022  flagged by " + reporter
                + (reason.isBlank() ? "" : "  \u2022  reason: " + reason));
        meta.setWrapText(true);
        meta.setStyle("-fx-font-size: 11px; -fx-text-fill: #71717a;");

        Button resolveBtn = UiKit.primaryButton("Mark Reviewed");
        resolveBtn.setOnAction(e -> {
            resolveBtn.setDisable(true);
            Async.run(
                    () -> { AdminService.resolveFlag(flagId); return null; },
                    v -> onChanged.run(),
                    err -> resolveBtn.setDisable(false)
            );
        });

        HBox actions = new HBox(resolveBtn);
        actions.setAlignment(Pos.CENTER_RIGHT);

        card.getChildren().addAll(content, meta, actions);
        return card;
    }

    private static String friendlyMessage(Exception err) {
        if (err instanceof ApiException ae) return ae.firstMessage();
        return "Couldn't reach the server. Check your connection and try again.";
    }
}
