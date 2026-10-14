package com.sdf.components;

import com.sdf.api.ApiException;
import com.sdf.api.Async;
import com.sdf.api.TopicService;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.layout.*;
import javafx.stage.StageStyle;

/**
 * "Create New Topic" modal — POST /api/groups/{group}/topics
 * (TopicController@store) via TopicService.create(groupId, title,
 * description, isPrivate). Reached from a group's "+ Add Topic" row in
 * the Discussions sidebar.
 */
public final class CreateTopicDialog {

    private CreateTopicDialog() {}

    public static void show(long groupId, Runnable onCreated) {
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
        Label title = new Label("Create New Topic");
        title.setStyle("-fx-font-size: 18px; -fx-font-weight: 800; -fx-text-fill: #18181b;");
        Region hspacer = new Region();
        HBox.setHgrow(hspacer, Priority.ALWAYS);
        Button closeX = new Button("\u2715");
        closeX.setStyle("-fx-background-color: transparent; -fx-text-fill: #a1a1aa; -fx-font-size: 14px; -fx-cursor: hand;");
        closeX.setOnAction(e -> dialog.close());
        header.getChildren().addAll(title, hspacer, closeX);

        VBox body = new VBox(8);
        body.setPadding(new Insets(20, 24, 8, 24));

        Label titleLabel = new Label("Topic Title");
        titleLabel.setStyle("-fx-font-size:13px; -fx-text-fill:#52525b; -fx-font-weight:600;");
        TextField titleField = new TextField();
        titleField.setPromptText("e.g., Assignment 1 Discussion");
        titleField.setStyle("-fx-background-color:white; -fx-border-color:#d4d4d8; -fx-border-radius:9; "
                + "-fx-background-radius:9; -fx-padding:9 12 9 12; -fx-font-size:13px;");
        VBox.setMargin(titleField, new Insets(0, 0, 14, 0));

        Label descLabel = new Label("Description");
        descLabel.setStyle("-fx-font-size:13px; -fx-text-fill:#52525b; -fx-font-weight:600;");
        TextArea descField = new TextArea();
        descField.setPromptText("Briefly describe what this topic is about...");
        descField.setPrefRowCount(3);
        descField.setWrapText(true);
        descField.setStyle("-fx-background-color:white; -fx-border-color:#d4d4d8; -fx-border-radius:9; "
                + "-fx-background-radius:9; -fx-padding:6; -fx-font-size:13px;");
        VBox.setMargin(descField, new Insets(0, 0, 14, 0));

        CheckBox privateBox = new CheckBox("Make this topic private (Requires approval to join)");
        privateBox.setStyle("-fx-font-size:12.5px; -fx-text-fill:#3f3f46;");

        Label error = new Label();
        error.setWrapText(true);
        error.setStyle("-fx-text-fill:#dc2626; -fx-font-size: 11.5px; -fx-padding: 10 0 0 0;");
        error.setManaged(false);
        error.setVisible(false);

        body.getChildren().addAll(titleLabel, titleField, descLabel, descField, privateBox, error);

        HBox footer = new HBox(14);
        footer.setAlignment(Pos.CENTER_RIGHT);
        footer.setPadding(new Insets(16, 24, 22, 24));
        footer.setStyle("-fx-border-color: #ececec transparent transparent transparent; -fx-border-width: 1 0 0 0;");
        Button cancelBtn = new Button("Cancel");
        cancelBtn.setStyle("-fx-background-color: transparent; -fx-text-fill:#3f3f46; -fx-font-weight:600; -fx-font-size:13px; -fx-cursor:hand;");
        cancelBtn.setOnAction(e -> dialog.close());
        Button saveBtn = new Button("Save Topic");
        saveBtn.setStyle("-fx-background-color:#2F7A54; -fx-text-fill:white; -fx-font-weight:700; -fx-font-size:13px; "
                + "-fx-background-radius:9; -fx-padding:10 22 10 22; -fx-cursor:hand;");
        saveBtn.setOnAction(e -> {
            String t = titleField.getText().trim();
            String d = descField.getText().trim();
            if (t.isEmpty() || d.isEmpty()) {
                error.setText("Please fill in both the title and description.");
                error.setManaged(true);
                error.setVisible(true);
                return;
            }
            saveBtn.setDisable(true);
            Async.run(
                    () -> { TopicService.create(groupId, t, d, privateBox.isSelected()); return null; },
                    v -> { dialog.close(); onCreated.run(); },
                    err -> {
                        saveBtn.setDisable(false);
                        error.setText(friendlyMessage(err));
                        error.setManaged(true);
                        error.setVisible(true);
                    }
            );
        });
        footer.getChildren().addAll(cancelBtn, saveBtn);

        card.getChildren().addAll(header, body, footer);
        pane.setContent(card);
        pane.getButtonTypes().add(ButtonType.CLOSE);
        pane.lookupButton(ButtonType.CLOSE).setVisible(false);
        pane.lookupButton(ButtonType.CLOSE).setManaged(false);
        dialog.setDialogPane(pane);
        dialog.showAndWait();
    }

    private static String friendlyMessage(Exception err) {
        if (err instanceof ApiException ae) return ae.firstMessage();
        return "Couldn't reach the server. Check your connection and try again.";
    }
}