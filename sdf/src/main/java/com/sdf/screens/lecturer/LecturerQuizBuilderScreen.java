package com.sdf.screens.lecturer;

import com.sdf.Router;
import com.sdf.api.ApiException;
import com.sdf.api.Async;
import com.sdf.api.GroupService;
import com.sdf.api.QuizService;
import com.sdf.components.Sidebar;
import com.sdf.components.UiKit;
import javafx.collections.FXCollections;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.*;
import javafx.util.StringConverter;

import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

public class LecturerQuizBuilderScreen {

    /** One editable question card in the builder. */
    private static final class QuestionCard {
        final VBox root = new VBox(10);
        final TextArea statement = new TextArea();
        final TextField optA = new TextField();
        final TextField optB = new TextField();
        final TextField optC = new TextField();
        final TextField optD = new TextField();
        final ComboBox<String> correct = new ComboBox<>(FXCollections.observableArrayList("A", "B", "C", "D"));
        final TextField points = new TextField("1");

        QuestionCard(Runnable onRemove) {
            root.setStyle("-fx-border-color:#e4e4e7; -fx-border-radius:12; -fx-padding:14;");
            statement.setPromptText("Question text\u2026");
            statement.setPrefRowCount(2);

            optA.setPromptText("Option A");
            optB.setPromptText("Option B");
            optC.setPromptText("Option C");
            optD.setPromptText("Option D");
            GridPane grid = new GridPane();
            grid.setHgap(8);
            grid.setVgap(8);
            grid.add(optA, 0, 0);
            grid.add(optB, 1, 0);
            grid.add(optC, 0, 1);
            grid.add(optD, 1, 1);
            ColumnConstraints c1 = new ColumnConstraints();
            c1.setPercentWidth(50);
            ColumnConstraints c2 = new ColumnConstraints();
            c2.setPercentWidth(50);
            grid.getColumnConstraints().addAll(c1, c2);

            correct.getSelectionModel().selectFirst();
            points.setPrefWidth(60);
            HBox metaRow = new HBox(10,
                    new Label("Correct:"), correct,
                    new Label("Points:"), points);
            metaRow.setAlignment(Pos.CENTER_LEFT);

            Button remove = new Button("Remove question");
            remove.setStyle("-fx-background-color:transparent; -fx-text-fill:#dc2626; -fx-font-size:11px; -fx-cursor:hand;");
            remove.setOnAction(e -> onRemove.run());
            HBox removeRow = new HBox(remove);
            removeRow.setAlignment(Pos.CENTER_RIGHT);

            root.getChildren().addAll(statement, grid, metaRow, removeRow);
        }

        QuizService.QuestionInput toInput() {
            Map<String, String> options = new LinkedHashMap<>();
            options.put("A", optA.getText().trim());
            options.put("B", optB.getText().trim());
            options.put("C", optC.getText().trim());
            options.put("D", optD.getText().trim());
            int pts;
            try {
                pts = Integer.parseInt(points.getText().trim());
            } catch (NumberFormatException nfe) {
                pts = 1;
            }
            return new QuizService.QuestionInput(statement.getText().trim(), pts, correct.getValue(), options);
        }

        boolean isValid() {
            return !statement.getText().trim().isEmpty()
                    && !optA.getText().trim().isEmpty() && !optB.getText().trim().isEmpty()
                    && !optC.getText().trim().isEmpty() && !optD.getText().trim().isEmpty()
                    && correct.getValue() != null;
        }
    }

    public static Parent build() {
        BorderPane root = new BorderPane();
        root.getStyleClass().add("app-shell");

        LinkedHashMap<String, Runnable> links = new LinkedHashMap<>();
        links.put("Dashboard", () -> Router.get().navigate(LecturerDashboardScreen::build));
        links.put("Students", () -> Router.get().navigate(LecturerStudentsScreen::build));
        links.put("Grades", () -> Router.get().navigate(LecturerGradesScreen::build));
        links.put("Quiz Control", null);
        links.put("Reports", () -> Router.get().navigate(LecturerReportsScreen::build));
        root.setLeft(new Sidebar("Quiz Control", com.sdf.session.Session.get().getInitial(), com.sdf.session.Session.get().getName(), links));

        VBox content = new VBox(20);
        content.getStyleClass().add("bg-zinc-page");
        content.setPadding(new Insets(28));

        Label title = new Label("Assessment Builder");
        title.setStyle("-fx-font-size: 22px; -fx-font-weight: 800; -fx-text-fill: #18181b;");

        // ---- existing quizzes ----
        VBox existingBox = UiKit.card();
        Label existingTitle = new Label("YOUR QUIZZES");
        existingTitle.setStyle("-fx-font-size: 12px; -fx-font-weight: 800; -fx-text-fill: #27272a;");
        VBox existingList = new VBox(10);
        Label loadingExisting = new Label("Loading\u2026");
        loadingExisting.setStyle("-fx-text-fill:#a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");
        existingList.getChildren().add(loadingExisting);
        existingBox.getChildren().addAll(existingTitle, existingList);

        // ---- builder ----
        HBox builderRow = new HBox(20);

        VBox settingsBox = UiKit.card();
        settingsBox.setPrefWidth(300);
        Label settingsTitle = new Label("QUIZ SETTINGS");
        settingsTitle.setStyle("-fx-font-size: 12px; -fx-font-weight: 800; -fx-text-fill: #27272a;");

        Label groupLabel = new Label("GROUP");
        groupLabel.getStyleClass().add("field-label");
        ComboBox<GroupService.Group> groupSelect = new ComboBox<>();
        groupSelect.setMaxWidth(Double.MAX_VALUE);
        groupSelect.setConverter(new StringConverter<>() {
            @Override public String toString(GroupService.Group g) { return g == null ? "" : g.name; }
            @Override public GroupService.Group fromString(String s) { return null; }
        });

        Label titleLabel = new Label("QUIZ TITLE");
        titleLabel.getStyleClass().add("field-label");
        TextField quizTitle = new TextField();
        quizTitle.setPromptText("e.g., Mid-Term Examination");

        Label descLabel = new Label("DESCRIPTION");
        descLabel.getStyleClass().add("field-label");
        TextArea desc = new TextArea();
        desc.setPromptText("Provide instructions for the students...");
        desc.setPrefRowCount(3);

        Label timeLimitLabel = new Label("TIME LIMIT (MINUTES)");
        timeLimitLabel.getStyleClass().add("field-label");
        TextField timeLimit = new TextField("30");

        Label statusLabel = new Label("STATUS");
        statusLabel.getStyleClass().add("field-label");
        ComboBox<String> statusBox = new ComboBox<>(FXCollections.observableArrayList("DRAFT", "PUBLISHED"));
        statusBox.getSelectionModel().selectFirst();
        statusBox.setMaxWidth(Double.MAX_VALUE);

        Label startLabel = new Label("START TIME (OPTIONAL, e.g. 2026-08-01 09:00:00)");
        startLabel.getStyleClass().add("field-label");
        TextField startTime = new TextField();
        startTime.setPromptText("Leave blank to open immediately");

        CheckBox autoSubmit = new CheckBox("Auto-submit when time runs out");
        autoSubmit.setSelected(true);

        settingsBox.getChildren().addAll(settingsTitle,
                groupLabel, groupSelect,
                titleLabel, quizTitle,
                descLabel, desc,
                timeLimitLabel, timeLimit,
                statusLabel, statusBox,
                startLabel, startTime,
                autoSubmit);

        VBox questionsBox = UiKit.card();
        HBox.setHgrow(questionsBox, Priority.ALWAYS);
        Label questionsTitle = new Label("QUESTIONS");
        questionsTitle.setStyle("-fx-font-size: 12px; -fx-font-weight: 800; -fx-text-fill: #27272a;");

        VBox questionCards = new VBox(14);
        List<QuestionCard> cards = new ArrayList<>();

        Runnable[] addQuestionHolder = new Runnable[1];
        addQuestionHolder[0] = () -> {
            QuestionCard[] cardHolder = new QuestionCard[1];
            cardHolder[0] = new QuestionCard(() -> {
                cards.remove(cardHolder[0]);
                questionCards.getChildren().remove(cardHolder[0].root);
            });
            cards.add(cardHolder[0]);
            questionCards.getChildren().add(cardHolder[0].root);
        };

        Hyperlink addQuestion = new Hyperlink("+ Add Question");
        addQuestion.getStyleClass().add("link-brand");
        addQuestion.setOnAction(e -> addQuestionHolder[0].run());

        Label errorLabel = new Label();
        errorLabel.setStyle("-fx-text-fill:#dc2626; -fx-font-size:12px; -fx-font-weight:600;");
        errorLabel.setWrapText(true);
        errorLabel.setManaged(false);
        errorLabel.setVisible(false);

        Button saveDraft = new Button("Save as Draft");
        saveDraft.setStyle("-fx-background-color:#f4f4f5; -fx-text-fill:#3f3f46; -fx-font-weight:800; -fx-background-radius:10; -fx-padding:8 16 8 16;");
        Button publishQuiz = new Button("Publish Quiz");
        publishQuiz.setStyle("-fx-background-color:#24a065; -fx-text-fill:white; -fx-font-weight:800; -fx-background-radius:10; -fx-padding:8 16 8 16;");
        HBox actionsRow = new HBox(10, saveDraft, publishQuiz);
        actionsRow.setAlignment(Pos.CENTER_RIGHT);

        questionsBox.getChildren().addAll(questionsTitle, questionCards, addQuestion, errorLabel, actionsRow);

        builderRow.getChildren().addAll(settingsBox, questionsBox);

        content.getChildren().addAll(title, existingBox, builderRow);
        ScrollPane scroll = new ScrollPane(content);
        scroll.setFitToWidth(true);
        scroll.getStyleClass().add("scroll-pane");
        root.setCenter(scroll);

        // start with one blank question so the form isn't empty
        addQuestionHolder[0].run();

        Runnable[] refreshExisting = new Runnable[1];
        refreshExisting[0] = () -> Async.run(
                QuizService::list,
                quizzes -> {
                    existingList.getChildren().clear();
                    if (quizzes.isEmpty()) {
                        Label empty = new Label("You haven't created any quizzes yet.");
                        empty.setStyle("-fx-text-fill:#a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");
                        existingList.getChildren().add(empty);
                    } else {
                        for (QuizService.QuizSummary q : quizzes) {
                            existingList.getChildren().add(existingQuizRow(q));
                        }
                    }
                },
                err -> {
                    existingList.getChildren().clear();
                    Label errLbl = new Label("Couldn't load your quizzes: " + friendlyMessage(err));
                    errLbl.setWrapText(true);
                    errLbl.setStyle("-fx-text-fill:#dc2626; -fx-font-size: 12px;");
                    existingList.getChildren().add(errLbl);
                }
        );
        refreshExisting[0].run();

        Async.run(
                GroupService::listManageable,
                groups -> {
                    groupSelect.setItems(FXCollections.observableArrayList(groups));
                    if (!groups.isEmpty()) groupSelect.getSelectionModel().selectFirst();
                },
                err -> { /* group select just stays empty; create button will report the error */ }
        );

        Runnable[] doCreate = new Runnable[1];
        doCreate[0] = () -> {
            errorLabel.setManaged(false);
            errorLabel.setVisible(false);

            GroupService.Group group = groupSelect.getValue();
            if (group == null) {
                showError(errorLabel, "Select a group for this quiz first.");
                return;
            }
            if (quizTitle.getText().trim().isEmpty()) {
                showError(errorLabel, "Give the quiz a title.");
                return;
            }
            int timeLimitMinutes;
            try {
                timeLimitMinutes = Integer.parseInt(timeLimit.getText().trim());
                if (timeLimitMinutes < 1) throw new NumberFormatException();
            } catch (NumberFormatException nfe) {
                showError(errorLabel, "Time limit must be a whole number of minutes (at least 1).");
                return;
            }
            if (cards.isEmpty()) {
                showError(errorLabel, "Add at least one question.");
                return;
            }
            for (QuestionCard c : cards) {
                if (!c.isValid()) {
                    showError(errorLabel, "Every question needs its text, all four options, and a correct answer.");
                    return;
                }
            }

            List<QuizService.QuestionInput> questions = cards.stream().map(QuestionCard::toInput).toList();
            saveDraft.setDisable(true);
            publishQuiz.setDisable(true);

            Async.run(
                    () -> {
                        QuizService.create(group.id, quizTitle.getText().trim(), desc.getText().trim(),
                                timeLimitMinutes, statusBox.getValue(), startTime.getText().trim(),
                                autoSubmit.isSelected(), questions);
                        return null;
                    },
                    v -> {
                        saveDraft.setDisable(false);
                        publishQuiz.setDisable(false);
                        quizTitle.clear();
                        desc.clear();
                        questionCards.getChildren().clear();
                        cards.clear();
                        addQuestionHolder[0].run();
                        refreshExisting[0].run();
                        new Alert(Alert.AlertType.INFORMATION, "Quiz saved.").showAndWait();
                    },
                    err -> {
                        saveDraft.setDisable(false);
                        publishQuiz.setDisable(false);
                        showError(errorLabel, friendlyMessage(err));
                    }
            );
        };

        saveDraft.setOnAction(e -> {
            statusBox.setValue("DRAFT");
            doCreate[0].run();
        });
        publishQuiz.setOnAction(e -> {
            statusBox.setValue("PUBLISHED");
            doCreate[0].run();
        });

        return root;
    }

    private static HBox existingQuizRow(QuizService.QuizSummary q) {
        HBox box = new HBox(10);
        box.setAlignment(Pos.CENTER_LEFT);
        box.setStyle("-fx-background-color:#fafafa; -fx-border-color:#e4e4e7; -fx-border-radius:10; -fx-background-radius:10; -fx-padding:14;");
        VBox text = new VBox(4);
        HBox nameRow = new HBox(8);
        nameRow.setAlignment(Pos.CENTER_LEFT);
        Label n = new Label(q.title);
        n.setStyle("-fx-font-size: 13px; -fx-font-weight: 800; -fx-text-fill: #18181b;");
        nameRow.getChildren().add(n);
        if (q.status != null && !q.status.isBlank()) {
            String color = "PUBLISHED".equalsIgnoreCase(q.status) ? "#059669" : "#d97706";
            Label tag = new Label(q.status.toUpperCase());
            tag.setStyle("-fx-font-size: 9px; -fx-font-weight: 800; -fx-text-fill:" + color + ";");
            nameRow.getChildren().add(tag);
        }
        StringBuilder meta = new StringBuilder();
        if (!q.groupLabel.isBlank()) meta.append(q.groupLabel);
        if (q.timeLimitMinutes != null) {
            if (meta.length() > 0) meta.append("  \u00B7  ");
            meta.append(q.timeLimitMinutes).append(" min");
        }
        Label m = new Label(meta.toString());
        m.setStyle("-fx-font-size: 11px; -fx-text-fill: #71717a;");
        text.getChildren().addAll(nameRow, m);
        HBox.setHgrow(text, Priority.ALWAYS);
        box.getChildren().add(text);
        return box;
    }

    private static void showError(Label errorLabel, String message) {
        errorLabel.setText(message);
        errorLabel.setManaged(true);
        errorLabel.setVisible(true);
    }

    private static String friendlyMessage(Exception err) {
        if (err instanceof ApiException ae) return ae.firstMessage();
        return "Couldn't reach the server. Check your connection and try again.";
    }
}
