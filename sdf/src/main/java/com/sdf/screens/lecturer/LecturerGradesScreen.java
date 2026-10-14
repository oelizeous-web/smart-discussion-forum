package com.sdf.screens.lecturer;

import com.sdf.Router;
import com.sdf.api.ApiException;
import com.sdf.api.Async;
import com.sdf.api.GroupService;
import com.sdf.api.RosterService;
import com.sdf.components.Sidebar;
import com.sdf.components.UiKit;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.BorderPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.VBox;
import javafx.util.StringConverter;

import java.util.LinkedHashMap;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Set;

/**
 * Grades Ledger, backed by real data: GET /api/groups/{group}/grades
 * (GroupController@grades / GroupService::getGroupLedger) returns every
 * recorded Mark for every quiz in the selected group \u2014 i.e. the real
 * gradebook, not just one quiz's report at a time.
 */
public class LecturerGradesScreen {

    private static List<RosterService.GradeRow> allRows = List.of();

    public static Parent build() {
        BorderPane root = new BorderPane();
        root.getStyleClass().add("app-shell");

        LinkedHashMap<String, Runnable> links = new LinkedHashMap<>();
        links.put("Dashboard", () -> Router.get().navigate(LecturerDashboardScreen::build));
        links.put("Students", () -> Router.get().navigate(LecturerStudentsScreen::build));
        links.put("Grades", null);
        links.put("Quiz Control", () -> Router.get().navigate(LecturerQuizBuilderScreen::build));
        links.put("Reports", () -> Router.get().navigate(LecturerReportsScreen::build));
        root.setLeft(new Sidebar("Grades", com.sdf.session.Session.get().getInitial(), com.sdf.session.Session.get().getName(), links));

        VBox content = new VBox(18);
        content.getStyleClass().add("bg-gray-page");
        content.setPadding(new Insets(28));

        Label title = new Label("Grades Ledger");
        title.setStyle("-fx-font-size: 20px; -fx-font-weight: 800; -fx-text-fill: #18181b;");
        Label sub = new Label("Every recorded score for this group's quizzes, pulled live from the marks table.");
        sub.getStyleClass().add("muted");
        sub.setWrapText(true);

        ComboBox<GroupService.Group> groupSelect = new ComboBox<>();
        groupSelect.setPrefWidth(260);
        groupSelect.setPromptText("Target study group\u2026");
        groupSelect.setConverter(new StringConverter<>() {
            @Override public String toString(GroupService.Group g) { return g == null ? "" : g.name; }
            @Override public GroupService.Group fromString(String s) { return null; }
        });

        ComboBox<String> quizFilter = new ComboBox<>();
        quizFilter.setPrefWidth(220);
        quizFilter.setValue("All Group Assessments");

        Label groupLbl = new Label("TARGET STUDY GROUP");
        groupLbl.setStyle("-fx-font-size: 10.5px; -fx-font-weight: 800; -fx-text-fill: #71717a;");
        Label filterLbl = new Label("FILTER BY ASSESSMENT");
        filterLbl.setStyle("-fx-font-size: 10.5px; -fx-font-weight: 800; -fx-text-fill: #71717a;");
        VBox groupCol = new VBox(6, groupLbl, groupSelect);
        VBox filterCol = new VBox(6, filterLbl, quizFilter);
        HBox pickerRow = new HBox(20, groupCol, filterCol);
        pickerRow.setAlignment(Pos.CENTER_LEFT);

        HBox scaleRow = new HBox(10,
                UiKit.badge("A  80-100%", "badge-green"),
                UiKit.badge("B  70-79%", "badge-outline-green"),
                UiKit.badge("C  60-69%", "badge-amber"),
                UiKit.badge("D  50-59%", "badge-outline-orange"),
                UiKit.badge("F  0-49%", "badge-red")
        );

        TableView<RosterService.GradeRow> table = new TableView<>();
        table.getStyleClass().add("data-table");
        table.setPrefHeight(420);
        table.setPlaceholder(new Label("Select a study group above to load its gradebook."));

        TableColumn<RosterService.GradeRow, String> studentCol = new TableColumn<>("Student Details");
        studentCol.setCellValueFactory(c -> new SimpleStringProperty(c.getValue().studentName));
        studentCol.setPrefWidth(200);

        TableColumn<RosterService.GradeRow, String> quizCol = new TableColumn<>("Assessment Activity");
        quizCol.setCellValueFactory(c -> new SimpleStringProperty(c.getValue().quizTitle));
        quizCol.setPrefWidth(220);

        TableColumn<RosterService.GradeRow, String> scoreCol = new TableColumn<>("Raw Score (/100)");
        scoreCol.setCellValueFactory(c -> new SimpleStringProperty(c.getValue().score + " / 100"));
        scoreCol.setPrefWidth(130);

        TableColumn<RosterService.GradeRow, RosterService.GradeRow> gradeCol = new TableColumn<>("Calculated Grade");
        gradeCol.setCellValueFactory(c -> new javafx.beans.property.SimpleObjectProperty<>(c.getValue()));
        gradeCol.setPrefWidth(120);
        gradeCol.setCellFactory(col -> new TableCell<>() {
            @Override
            protected void updateItem(RosterService.GradeRow row, boolean empty) {
                super.updateItem(row, empty);
                setGraphic(empty || row == null ? null : UiKit.badge(row.grade, badgeClassFor(row.grade)));
            }
        });

        table.getColumns().addAll(studentCol, quizCol, scoreCol, gradeCol);

        content.getChildren().addAll(title, sub, pickerRow, scaleRow, table);
        root.setCenter(content);

        Runnable applyFilter = () -> {
            String quiz = quizFilter.getValue();
            ObservableList<RosterService.GradeRow> filtered = FXCollections.observableArrayList(
                    (quiz == null || quiz.equals("All Group Assessments"))
                            ? allRows
                            : allRows.stream().filter(r -> r.quizTitle.equals(quiz)).toList()
            );
            table.setItems(filtered);
            if (filtered.isEmpty()) {
                table.setPlaceholder(new Label("No submissions recorded yet."));
            }
        };

        Runnable loadLedger = () -> {
            GroupService.Group selected = groupSelect.getValue();
            if (selected == null) return;
            table.getItems().clear();
            table.setPlaceholder(new Label("Loading\u2026"));
            Async.run(
                    () -> RosterService.grades(selected.id),
                    rows -> {
                        allRows = rows;
                        Set<String> quizTitles = new LinkedHashSet<>();
                        quizTitles.add("All Group Assessments");
                        for (RosterService.GradeRow r : rows) quizTitles.add(r.quizTitle);
                        quizFilter.setItems(FXCollections.observableArrayList(quizTitles));
                        quizFilter.setValue("All Group Assessments");
                        applyFilter.run();
                    },
                    err -> table.setPlaceholder(new Label("Couldn't load grades: " + friendlyMessage(err)))
            );
        };

        groupSelect.valueProperty().addListener((obs, oldV, newV) -> loadLedger.run());
        quizFilter.valueProperty().addListener((obs, oldV, newV) -> applyFilter.run());

        Async.run(
                GroupService::listManageable,
                groups -> {
                    groupSelect.setItems(FXCollections.observableArrayList(groups));
                    if (!groups.isEmpty()) {
                        groupSelect.getSelectionModel().selectFirst();
                    } else {
                        table.setPlaceholder(new Label("You aren't leading any groups yet."));
                    }
                },
                err -> table.setPlaceholder(new Label("Couldn't load your groups: " + friendlyMessage(err)))
        );

        return root;
    }

    private static String badgeClassFor(String grade) {
        if (grade == null) return "badge-outline-orange";
        return switch (grade) {
            case "A" -> "badge-green";
            case "B" -> "badge-outline-green";
            case "C" -> "badge-amber";
            case "D" -> "badge-outline-orange";
            default -> "badge-red"; // F
        };
    }

    private static String friendlyMessage(Exception err) {
        if (err instanceof ApiException ae) return ae.firstMessage();
        return "Couldn't reach the server. Check your connection and try again.";
    }
}
