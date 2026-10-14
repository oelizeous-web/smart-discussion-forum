package com.sdf.screens.admin;

import com.sdf.Router;
import com.sdf.api.ApiException;
import com.sdf.api.Async;
import com.sdf.api.JsonTable;
import com.sdf.api.QuizService;
import com.sdf.components.Sidebar;
import com.sdf.components.UiKit;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.control.Button;
import javafx.scene.control.ComboBox;
import javafx.scene.control.Label;
import javafx.scene.control.TableColumn;
import javafx.scene.control.TableView;
import javafx.scene.layout.BorderPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;
import javafx.util.StringConverter;

import java.util.LinkedHashMap;

/**
 * Your api.php has no group-level analytics endpoint, only a per-quiz
 * report: GET /api/quizzes/{quiz}/report (role:lecturer|admin, so this
 * IS reachable by an admin account) \u2014 CONFIRMED. So this screen is a
 * quiz picker + that report, rather than the platform-wide analytics
 * shown on the web app's /dashboard-style pages.
 */
public class AdminReportsScreen {

    public static Parent build() {
        BorderPane root = new BorderPane();
        root.getStyleClass().add("app-shell");

        LinkedHashMap<String, Runnable> links = new LinkedHashMap<>();
        links.put("Dashboard", () -> Router.get().navigate(AdminDashboardScreen::build));
        links.put("Members", () -> Router.get().navigate(AdminMembersScreen::build));
        links.put("Settings", () -> Router.get().navigate(AdminSettingsScreen::build));
        links.put("Reports", null);
        root.setLeft(new Sidebar("Reports", com.sdf.session.Session.get().getInitial(), com.sdf.session.Session.get().getName(), links));

        VBox content = new VBox(18);
        content.getStyleClass().add("bg-gray-page");
        content.setPadding(new Insets(28));

        Label title = new Label("Quiz Performance Reports");
        title.setStyle("-fx-font-size: 20px; -fx-font-weight: 800; -fx-text-fill: #18181b;");
        Label sub = new Label("Pick any quiz on the platform to see how it went.");
        sub.getStyleClass().add("muted");

        ComboBox<QuizService.QuizSummary> quizSelect = new ComboBox<>();
        quizSelect.setPrefWidth(360);
        quizSelect.setConverter(new StringConverter<>() {
            @Override public String toString(QuizService.QuizSummary q) { return q == null ? "" : q.title; }
            @Override public QuizService.QuizSummary fromString(String s) { return null; }
        });
        Button loadBtn = UiKit.primaryButton("Load Report");
        HBox pickerRow = new HBox(10, quizSelect, loadBtn);
        pickerRow.setAlignment(Pos.CENTER_LEFT);

        Label statusLabel = new Label("Loading quizzes\u2026");
        statusLabel.setStyle("-fx-text-fill:#a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");

        TableView<JsonTable.Row> table = new TableView<>();
        table.getStyleClass().add("data-table");
        table.setPrefHeight(380);
        table.setPlaceholder(new Label("Select a quiz above and click \"Load Report\"."));

        content.getChildren().addAll(title, sub, pickerRow, statusLabel, table);
        root.setCenter(content);

        Async.run(
                QuizService::list,
                quizzes -> {
                    statusLabel.setVisible(false);
                    statusLabel.setManaged(false);
                    quizSelect.setItems(FXCollections.observableArrayList(quizzes));
                    if (!quizzes.isEmpty()) quizSelect.getSelectionModel().selectFirst();
                },
                err -> {
                    statusLabel.setText("Couldn't load quizzes: " + friendlyMessage(err));
                    statusLabel.setStyle("-fx-text-fill:#dc2626; -fx-font-size: 12px;");
                }
        );

        loadBtn.setOnAction(e -> {
            QuizService.QuizSummary quiz = quizSelect.getValue();
            if (quiz == null) return;
            table.getColumns().clear();
            table.getItems().clear();
            table.setPlaceholder(new Label("Loading report\u2026"));

            Async.run(
                    () -> QuizService.report(quiz.id),
                    json -> {
                        JsonTable.Result parsed = JsonTable.parse(json);
                        if (parsed.rows.isEmpty()) {
                            table.setPlaceholder(new Label("No submissions for this quiz yet."));
                            return;
                        }
                        for (String col : parsed.columns) {
                            TableColumn<JsonTable.Row, String> c = new TableColumn<>(col);
                            c.setCellValueFactory(cell -> new SimpleStringProperty(cell.getValue().cells.getOrDefault(col, "")));
                            table.getColumns().add(c);
                        }
                        table.setItems(FXCollections.observableArrayList(parsed.rows));
                    },
                    err -> {
                        String msg = (err instanceof ApiException ae && ae.getStatusCode() == 403)
                                ? "This report unlocks once the quiz's time limit has passed for everyone."
                                : friendlyMessage(err);
                        table.setPlaceholder(new Label(msg));
                    }
            );
        });

        return root;
    }

    private static String friendlyMessage(Exception err) {
        if (err instanceof ApiException ae) return ae.firstMessage();
        return "Couldn't reach the server. Check your connection and try again.";
    }
}
