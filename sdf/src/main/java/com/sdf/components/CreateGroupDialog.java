package com.sdf.components;

import com.sdf.api.ApiException;
import com.sdf.api.Async;
import com.sdf.api.GroupService;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.layout.*;
import javafx.stage.StageStyle;

/**
 * "Create New Group" modal. POST /api/groups (GroupController@store) creates
 * the group AND its first topic in one call, so this form collects exactly
 * the three fields it validates: name, topic_title, topic_description.
 *
 * Styled like {@link PlatformRulesDialog} — a transparent-stage Dialog
 * holding a plain white card — so it renders as a centered modal card
 * instead of a native OS dialog window.
 */
public final class CreateGroupDialog {

    private CreateGroupDialog() {}

    public static void show(Runnable onCreated) {
        Dialog<Void> dialog = new Dialog<>();
        dialog.initStyle(StageStyle.TRANSPARENT);

        DialogPane pane = new DialogPane();
        pane.setStyle("-fx-background-color: transparent;");

        VBox card = new VBox(0);
        card.setMaxWidth(460);
        card.setPrefWidth(460);
        card.setStyle("-fx-background-color: white; -fx-background-radius: 16; "
                + "-fx-effect: dropshadow(gaussian, rgba(0,0,0,0.28), 28, 0, 0, 10);");

        HBox header = new HBox();
        header.setAlignment(Pos.CENTER_LEFT);
        header.setPadding(new Insets(20, 24, 18, 24));
        header.setStyle("-fx-border-color: transparent transparent #ececec transparent; -fx-border-width: 0 0 1 0;");
        Label title = new Label("Create New Group");
        title.setStyle("-fx-font-size: 18px; -fx-font-weight: 800; -fx-text-fill: #18181b;");
        Region hspacer = new Region();
        HBox.setHgrow(hspacer, Priority.ALWAYS);
        Button closeX = new Button("\u2715");
        closeX.setStyle("-fx-background-color: transparent; -fx-text-fill: #a1a1aa; -fx-font-size: 14px; -fx-cursor: hand;");
        closeX.setOnAction(e -> dialog.close());
        header.getChildren().addAll(title, hspacer, closeX);

        VBox body = new VBox(8);
        body.setPadding(new Insets(20, 24, 8, 24));

        Label nameLabel = requiredLabel("GROUP NAME");
        TextField nameField = new TextField();
        nameField.setPromptText("e.g., Advanced Database Systems");
        styleInput(nameField);
        VBox.setMargin(nameField, new Insets(0, 0, 14, 0));

        Label topicLabel = requiredLabel("INITIAL TOPIC");
        Label topicHelper = new Label("Every group needs at least one topic to start discussions.");
        topicHelper.setWrapText(true);
        topicHelper.setStyle("-fx-font-size:11.5px; -fx-text-fill:#a1a1aa;");
        VBox.setMargin(topicHelper, new Insets(2, 0, 6, 0));

        TextField topicField = new TextField();
        topicField.setPromptText("e.g., Normalization Formats");
        topicField.setStyle("-fx-background-color:transparent; -fx-padding:8 0 8 0; -fx-font-size:13px;");
        HBox.setHgrow(topicField, Priority.ALWAYS);
        Label hash = new Label("#");
        hash.setStyle("-fx-text-fill:#a1a1aa; -fx-font-weight:700;");
        HBox topicInputRow = new HBox(6, hash, topicField);
        topicInputRow.setAlignment(Pos.CENTER_LEFT);
        topicInputRow.setPadding(new Insets(2, 10, 2, 10));
        topicInputRow.setStyle("-fx-background-color:white; -fx-border-color:#d4d4d8; -fx-border-radius:9; -fx-background-radius:9;");
        VBox.setMargin(topicInputRow, new Insets(0, 0, 14, 0));

        Label descLabel = requiredLabel("DESCRIPTION");
        TextArea descField = new TextArea();
        descField.setPromptText("What is the topic about?");
        descField.setPrefRowCount(3);
        descField.setWrapText(true);
        styleInput(descField);

        Label error = new Label();
        error.setWrapText(true);
        error.setStyle("-fx-text-fill:#dc2626; -fx-font-size: 11.5px; -fx-padding: 10 0 0 0;");
        error.setManaged(false);
        error.setVisible(false);

        body.getChildren().addAll(nameLabel, nameField, topicLabel, topicHelper, topicInputRow, descLabel, descField, error);

        HBox footer = new HBox(14);
        footer.setAlignment(Pos.CENTER_RIGHT);
        footer.setPadding(new Insets(16, 24, 22, 24));
        footer.setStyle("-fx-border-color: #ececec transparent transparent transparent; -fx-border-width: 1 0 0 0;");
        Button cancelBtn = new Button("Cancel");
        cancelBtn.setStyle("-fx-background-color: transparent; -fx-text-fill:#3f3f46; -fx-font-weight:600; -fx-font-size:13px; -fx-cursor:hand;");
        cancelBtn.setOnAction(e -> dialog.close());
        Button createBtn = new Button("Create");
        createBtn.setStyle("-fx-background-color:#2F7A54; -fx-text-fill:white; -fx-font-weight:700; -fx-font-size:13px; "
                + "-fx-background-radius:9; -fx-padding:10 22 10 22; -fx-cursor:hand;");
        createBtn.setOnAction(e -> {
            String name = nameField.getText().trim();
            String topicTitle = topicField.getText().trim();
            String desc = descField.getText().trim();
            if (name.isEmpty() || topicTitle.isEmpty() || desc.isEmpty()) {
                showError(error, "Please fill in every field.");
                return;
            }
            createBtn.setDisable(true);
            Async.run(
                    () -> { GroupService.create(name, topicTitle, desc); return null; },
                    v -> { dialog.close(); onCreated.run(); },
                    err -> {
                        createBtn.setDisable(false);
                        showError(error, friendlyMessage(err));
                    }
            );
        });
        footer.getChildren().addAll(cancelBtn, createBtn);

        card.getChildren().addAll(header, body, footer);
        pane.setContent(card);
        pane.getButtonTypes().add(ButtonType.CLOSE);
        pane.lookupButton(ButtonType.CLOSE).setVisible(false);
        pane.lookupButton(ButtonType.CLOSE).setManaged(false);
        dialog.setDialogPane(pane);
        dialog.showAndWait();
    }

    private static Label requiredLabel(String text) {
        Label l = new Label(text + " *");
        l.setStyle("-fx-font-size:11.5px; -fx-font-weight:800; -fx-text-fill:#3f3f46;");
        return l;
    }

    private static void styleInput(Control c) {
        c.setStyle("-fx-background-color:white; -fx-border-color:#d4d4d8; -fx-border-radius:9; -fx-background-radius:9; -fx-padding:8; -fx-font-size:13px;");
    }

    private static void showError(Label error, String msg) {
        error.setText(msg);
        error.setManaged(true);
        error.setVisible(true);
    }

    private static String friendlyMessage(Exception err) {
        if (err instanceof ApiException ae) return ae.firstMessage();
        return "Couldn't reach the server. Check your connection and try again.";
    }
}