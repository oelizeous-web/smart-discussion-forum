package com.sdf.screens.lecturer;

import com.sdf.Router;
import com.sdf.api.ApiException;
import com.sdf.api.Async;
import com.sdf.api.GroupService;
import com.sdf.api.RosterService;
import com.sdf.api.TopicService;
import com.sdf.components.Sidebar;
import com.sdf.components.UiKit;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.geometry.Insets;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.BorderPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.VBox;
import javafx.util.StringConverter;

import java.util.LinkedHashMap;

/**
 * Students Directory, backed by real data: GET /api/groups/{group}/students
 * (GroupController@students / GroupService::getGroupRoster) returns every
 * enrolled member of the selected group, their account standing (from the
 * blacklist table), and their average score across that group's quizzes.
 *
 * The moderation actions (approve topic access, issue a warning) are kept
 * alongside the table since those are the only *write* actions your API
 * exposes for students.
 */
public class LecturerStudentsScreen {

    public static Parent build() {
        BorderPane root = new BorderPane();
        root.getStyleClass().add("app-shell");

        LinkedHashMap<String, Runnable> links = new LinkedHashMap<>();
        links.put("Dashboard", () -> Router.get().navigate(LecturerDashboardScreen::build));
        links.put("Students", null);
        links.put("Grades", () -> Router.get().navigate(LecturerGradesScreen::build));
        links.put("Quiz Control", () -> Router.get().navigate(LecturerQuizBuilderScreen::build));
        links.put("Reports", () -> Router.get().navigate(LecturerReportsScreen::build));
        root.setLeft(new Sidebar("Students", com.sdf.session.Session.get().getInitial(), com.sdf.session.Session.get().getName(), links));

        VBox content = new VBox(18);
        content.getStyleClass().add("bg-white-page");
        content.setPadding(new Insets(28));

        Label title = new Label("Students Directory");
        title.setStyle("-fx-font-size: 22px; -fx-font-weight: 800; -fx-text-fill: #18181b;");
        Label sub = new Label("Manage active student profiles, review standings, and track platform records.");
        sub.getStyleClass().add("muted");
        sub.setWrapText(true);

        ComboBox<GroupService.Group> groupSelect = new ComboBox<>();
        groupSelect.setPrefWidth(280);
        groupSelect.setPromptText("Select a study group\u2026");
        groupSelect.setConverter(new StringConverter<>() {
            @Override public String toString(GroupService.Group g) { return g == null ? "" : g.name; }
            @Override public GroupService.Group fromString(String s) { return null; }
        });
        Label groupPickerLabel = new Label("ACTIVE STUDY GROUP");
        groupPickerLabel.setStyle("-fx-font-size: 11px; -fx-font-weight: 800; -fx-text-fill: #71717a;");
        VBox groupPicker = new VBox(6, groupPickerLabel, groupSelect);

        HBox middle = new HBox(20);

        VBox tableBox = UiKit.card();
        HBox.setHgrow(tableBox, Priority.ALWAYS);
        Label tableTitle = new Label("CLASS ROSTER");
        tableTitle.setStyle("-fx-font-size: 12px; -fx-font-weight: 800; -fx-text-fill: #27272a;");

        TableView<RosterService.StudentRow> table = new TableView<>();
        table.getStyleClass().add("data-table");
        table.setPrefHeight(420);
        table.setPlaceholder(new Label("Select a study group above to load its roster."));

        TableColumn<RosterService.StudentRow, String> nameCol = new TableColumn<>("Student Details");
        nameCol.setCellValueFactory(c -> new SimpleStringProperty(c.getValue().name + "  (#" + c.getValue().id + ")"));
        nameCol.setPrefWidth(200);

        TableColumn<RosterService.StudentRow, String> emailCol = new TableColumn<>("Email Address");
        emailCol.setCellValueFactory(c -> new SimpleStringProperty(c.getValue().email));
        emailCol.setPrefWidth(200);

        TableColumn<RosterService.StudentRow, String> quizzesCol = new TableColumn<>("Quizzes Taken");
        quizzesCol.setCellValueFactory(c -> new SimpleStringProperty(String.valueOf(c.getValue().quizzesTaken)));
        quizzesCol.setPrefWidth(100);

        TableColumn<RosterService.StudentRow, String> avgCol = new TableColumn<>("Avg Score");
        avgCol.setCellValueFactory(c -> new SimpleStringProperty(
                c.getValue().averageScore == null ? "\u2014" : (c.getValue().averageScore + "%")));
        avgCol.setPrefWidth(90);

        TableColumn<RosterService.StudentRow, RosterService.StudentRow> standingCol = new TableColumn<>("Standing");
        standingCol.setCellValueFactory(c -> new javafx.beans.property.SimpleObjectProperty<>(c.getValue()));
        standingCol.setPrefWidth(140);
        standingCol.setCellFactory(col -> new TableCell<>() {
            @Override
            protected void updateItem(RosterService.StudentRow row, boolean empty) {
                super.updateItem(row, empty);
                if (empty || row == null) {
                    setGraphic(null);
                } else {
                    setGraphic(UiKit.badge(row.standing, badgeClassFor(row.standing)));
                }
            }
        });

        table.getColumns().addAll(nameCol, emailCol, quizzesCol, avgCol, standingCol);
        tableBox.getChildren().addAll(tableTitle, table);

        VBox actionsBox = UiKit.card();
        Label actionsTitle = new Label("MODERATION ACTIONS");
        actionsTitle.setStyle("-fx-font-size: 12px; -fx-font-weight: 800; -fx-text-fill: #27272a;");

        // Shared "which topic" context for both actions below -- real group/topic
        // titles instead of raw ids. Sourced from GroupService.list() (/api/groups)
        // because that's the endpoint that actually eager-loads real topic titles;
        // /groups/manageable (used for the roster's group picker above) only ever
        // returns a topic count, and the dedicated /groups/{id}/topics endpoint
        // that would give titles per-group is broken server-side (no controller
        // method), so this picker is scoped to groups you lead or belong to.
        Label modGroupLabel = new Label("GROUP");
        modGroupLabel.setStyle("-fx-font-size: 10.5px; -fx-font-weight: 800; -fx-text-fill: #71717a;");
        ComboBox<GroupService.Group> modGroupSelect = new ComboBox<>();
        modGroupSelect.setMaxWidth(Double.MAX_VALUE);
        modGroupSelect.setPromptText("Select a group\u2026");
        modGroupSelect.setConverter(new StringConverter<>() {
            @Override public String toString(GroupService.Group g) { return g == null ? "" : g.name; }
            @Override public GroupService.Group fromString(String s) { return null; }
        });

        Label modTopicLabel = new Label("TOPIC");
        modTopicLabel.setStyle("-fx-font-size: 10.5px; -fx-font-weight: 800; -fx-text-fill: #71717a;");
        ComboBox<GroupService.Topic> modTopicSelect = new ComboBox<>();
        modTopicSelect.setMaxWidth(Double.MAX_VALUE);
        modTopicSelect.setPromptText("Select a group first\u2026");
        modTopicSelect.setConverter(new StringConverter<>() {
            @Override public String toString(GroupService.Topic t) { return t == null ? "" : t.title; }
            @Override public GroupService.Topic fromString(String s) { return null; }
        });

        modGroupSelect.valueProperty().addListener((obs, oldV, newV) -> {
            if (newV == null) {
                modTopicSelect.setItems(FXCollections.observableArrayList());
                modTopicSelect.setPromptText("Select a group first\u2026");
                modTopicSelect.setDisable(true);
                return;
            }
            modTopicSelect.setItems(FXCollections.observableArrayList(newV.topics));
            if (newV.topics.isEmpty()) {
                modTopicSelect.setPromptText("This group has no topics yet");
                modTopicSelect.setDisable(true);
            } else {
                modTopicSelect.setPromptText("Select a topic\u2026");
                modTopicSelect.setDisable(false);
                modTopicSelect.getSelectionModel().selectFirst();
            }
        });

        VBox modContextBox = new VBox(6, modGroupLabel, modGroupSelect, modTopicLabel, modTopicSelect);

        Label approveHeading = new Label("Approve topic access request");
        approveHeading.setStyle("-fx-font-weight: 700; -fx-font-size: 12px; -fx-text-fill: #18181b;");
        ComboBox<RosterService.StudentRow> approveStudentSelect = new ComboBox<>();
        approveStudentSelect.setMaxWidth(Double.MAX_VALUE);
        approveStudentSelect.setPromptText("Select a student\u2026");
        approveStudentSelect.setConverter(studentNameConverter());
        Button approveBtn = new Button("Approve");
        approveBtn.getStyleClass().add("btn-primary");
        Label approveStatus = new Label();
        approveStatus.setWrapText(true);
        approveStatus.setStyle("-fx-font-size: 11px;");
        VBox approveBox = new VBox(6, approveHeading, approveStudentSelect, approveBtn, approveStatus);

        Label warnHeading = new Label("Issue a moderation warning");
        warnHeading.setStyle("-fx-font-weight: 700; -fx-font-size: 12px; -fx-text-fill: #18181b;");
        ComboBox<RosterService.StudentRow> warnStudentSelect = new ComboBox<>();
        warnStudentSelect.setMaxWidth(Double.MAX_VALUE);
        warnStudentSelect.setPromptText("Select a student\u2026");
        warnStudentSelect.setConverter(studentNameConverter());
        Button warnBtn = new Button("Issue Warning");
        warnBtn.getStyleClass().add("btn-danger");
        Label warnStatus = new Label();
        warnStatus.setWrapText(true);
        warnStatus.setStyle("-fx-font-size: 11px;");
        VBox warnBox = new VBox(6, warnHeading, warnStudentSelect, warnBtn, warnStatus);

        Label tip = new Label("Tip: pick the group and topic above once, then choose the student to approve or warn for it.");
        tip.setWrapText(true);
        tip.setStyle("-fx-font-size: 10.5px; -fx-text-fill: #a1a1aa; -fx-font-style: italic;");

        HBox actionsRow = new HBox(28, modContextBox, approveBox, warnBox);
        HBox.setHgrow(modContextBox, Priority.ALWAYS);
        HBox.setHgrow(approveBox, Priority.ALWAYS);
        HBox.setHgrow(warnBox, Priority.ALWAYS);

        actionsBox.getChildren().addAll(actionsTitle, actionsRow, tip);
        middle.getChildren().addAll(tableBox);

        content.getChildren().addAll(title, sub, groupPicker, middle, actionsBox);
        ScrollPane scroll = new ScrollPane(content);
        scroll.setFitToWidth(true);
        scroll.getStyleClass().add("scroll-pane");
        root.setCenter(scroll);

        Runnable loadRoster = () -> {
            GroupService.Group selected = groupSelect.getValue();
            if (selected == null) return;
            table.getItems().clear();
            table.setPlaceholder(new Label("Loading\u2026"));
            Async.run(
                    () -> RosterService.students(selected.id),
                    rows -> {
                        if (rows.isEmpty()) {
                            table.setPlaceholder(new Label("No students enrolled in this group yet."));
                        }
                        table.setItems(FXCollections.observableArrayList(rows));
                        approveStudentSelect.setItems(FXCollections.observableArrayList(rows));
                        warnStudentSelect.setItems(FXCollections.observableArrayList(rows));
                    },
                    err -> table.setPlaceholder(new Label("Couldn't load the roster: " + friendlyMessage(err)))
            );
        };

        groupSelect.valueProperty().addListener((obs, oldV, newV) -> loadRoster.run());

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

        modTopicSelect.setDisable(true);
        Async.run(
                GroupService::list,
                result -> {
                    modGroupSelect.setItems(FXCollections.observableArrayList(result.myGroups));
                    if (result.myGroups.isEmpty()) {
                        modGroupSelect.setPromptText("No groups available");
                    } else {
                        modGroupSelect.getSelectionModel().selectFirst();
                    }
                },
                err -> setStatus(approveStatus, "Couldn't load groups/topics: " + friendlyMessage(err), true)
        );

        approveBtn.setOnAction(e -> {
            GroupService.Topic topic = modTopicSelect.getValue();
            RosterService.StudentRow student = approveStudentSelect.getValue();
            if (topic == null || student == null) {
                setStatus(approveStatus, "Select a group, topic, and student first.", true);
                return;
            }
            approveBtn.setDisable(true);
            Async.run(
                    () -> { TopicService.approve(topic.id, student.id); return null; },
                    v -> {
                        approveBtn.setDisable(false);
                        setStatus(approveStatus, "Approved.", false);
                    },
                    err -> {
                        approveBtn.setDisable(false);
                        setStatus(approveStatus, friendlyMessage(err), true);
                    }
            );
        });

        warnBtn.setOnAction(e -> {
            GroupService.Topic topic = modTopicSelect.getValue();
            RosterService.StudentRow student = warnStudentSelect.getValue();
            if (topic == null || student == null) {
                setStatus(warnStatus, "Select a group, topic, and student first.", true);
                return;
            }
            warnBtn.setDisable(true);
            Async.run(
                    () -> { TopicService.warn(topic.id, student.id); return null; },
                    v -> {
                        warnBtn.setDisable(false);
                        setStatus(warnStatus, "Warning issued.", false);
                        loadRoster.run();
                    },
                    err -> {
                        warnBtn.setDisable(false);
                        setStatus(warnStatus, friendlyMessage(err), true);
                    }
            );
        });

        return root;
    }

    private static String badgeClassFor(String standing) {
        if (standing == null) return "badge-outline-orange";
        return switch (standing) {
            case "Excellent" -> "badge-green";
            case "Good" -> "badge-outline-green";
            case "Satisfactory" -> "badge-amber";
            case "At Risk" -> "badge-red";
            default -> "badge-outline-orange"; // "Not Yet Assessed"
        };
    }

    private static void setStatus(Label label, String text, boolean isError) {
        label.setText(text);
        label.setStyle("-fx-font-size: 11px; -fx-text-fill:" + (isError ? "#dc2626" : "#16a34a") + ";");
    }

    private static StringConverter<RosterService.StudentRow> studentNameConverter() {
        return new StringConverter<>() {
            @Override public String toString(RosterService.StudentRow row) { return row == null ? "" : row.name; }
            @Override public RosterService.StudentRow fromString(String s) { return null; }
        };
    }

    private static String friendlyMessage(Exception err) {
        if (err instanceof ApiException ae) return ae.firstMessage();
        return "Couldn't reach the server. Check your connection and try again.";
    }
}