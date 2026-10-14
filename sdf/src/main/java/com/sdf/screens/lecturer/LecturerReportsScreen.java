package com.sdf.screens.lecturer;

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
import javafx.print.PrinterJob;
import javafx.scene.Parent;
import javafx.scene.control.Alert;
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
 * Your api.php has no group-level analytics endpoint (no average
 * score / forum response rate / at-risk alerts) \u2014 the web app's
 * Academic Performance page is a separate server-rendered page. The
 * one real, confirmed report your API exposes is per-quiz:
 * GET /api/quizzes/{quiz}/report.
 */
public class LecturerReportsScreen {

    public static Parent build() {
        BorderPane root = new BorderPane();
        root.getStyleClass().add("app-shell");

        LinkedHashMap<String, Runnable> links = new LinkedHashMap<>();
        links.put("Dashboard", () -> Router.get().navigate(LecturerDashboardScreen::build));
        links.put("Students", () -> Router.get().navigate(LecturerStudentsScreen::build));
        links.put("Grades", () -> Router.get().navigate(LecturerGradesScreen::build));
        links.put("Quiz Control", () -> Router.get().navigate(LecturerQuizBuilderScreen::build));
        links.put("Reports", null);
        root.setLeft(new Sidebar("Reports", com.sdf.session.Session.get().getInitial(), com.sdf.session.Session.get().getName(), links));

        VBox content = new VBox(18);
        content.getStyleClass().add("bg-gray-page");
        content.setPadding(new Insets(28));

        Label title = new Label("Quiz Performance Reports");
        title.setStyle("-fx-font-size: 20px; -fx-font-weight: 800; -fx-text-fill: #18181b;");
        Label sub = new Label("Pick one of your quizzes to see how the group performed.");
        sub.getStyleClass().add("muted");

        ComboBox<QuizService.QuizSummary> quizSelect = new ComboBox<>();
        quizSelect.setPrefWidth(340);
        quizSelect.setConverter(new StringConverter<>() {
            @Override public String toString(QuizService.QuizSummary q) { return q == null ? "" : q.title; }
            @Override public QuizService.QuizSummary fromString(String s) { return null; }
        });
        Button loadBtn = UiKit.primaryButton("Load Report");
        Button printBtn = UiKit.outlineButton("\uD83D\uDDA8  Print");
        HBox pickerRow = new HBox(10, quizSelect, loadBtn, printBtn);
        pickerRow.setAlignment(Pos.CENTER_LEFT);

        Label statusLabel = new Label("Loading your quizzes\u2026");
        statusLabel.setStyle("-fx-text-fill:#a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");

        TableView<JsonTable.Row> table = new TableView<>();
        table.getStyleClass().add("data-table");
        table.setPrefHeight(400);
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
                    statusLabel.setText("Couldn't load your quizzes: " + friendlyMessage(err));
                    statusLabel.setStyle("-fx-text-fill:#dc2626; -fx-font-size: 12px;");
                }
        );

        loadBtn.setOnAction(e -> {
            QuizService.QuizSummary quiz = quizSelect.getValue();
            if (quiz == null) return;
            table.getColumns().clear();
            table.getItems().clear();
            table.setPlaceholder(new Label("Loading report\u2026"));
            loadBtn.setDisable(true);

            Async.run(
                    () -> QuizService.report(quiz.id),
                    json -> {
                        loadBtn.setDisable(false);
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
                        loadBtn.setDisable(false);
                        String msg = (err instanceof ApiException ae && ae.getStatusCode() == 403)
                                ? "This report unlocks once the quiz's time limit has passed for everyone."
                                : friendlyMessage(err);
                        table.setPlaceholder(new Label(msg));
                    }
            );
        });

        printBtn.setOnAction(e -> {
            PrinterJob job = PrinterJob.createPrinterJob();
            if (job == null) {
                new Alert(Alert.AlertType.INFORMATION, "No printer/PDF driver available on this machine.").showAndWait();
                return;
            }
            if (job.showPrintDialog(root.getScene().getWindow())) {
                if (job.printPage(table)) job.endJob();
            }
        });

        return root;
    }

    private static String friendlyMessage(Exception err) {
        if (err instanceof ApiException ae) return ae.firstMessage();
        return "Couldn't reach the server. Check your connection and try again.";
    }
}
