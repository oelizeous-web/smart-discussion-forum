package com.sdf.components;

import com.sdf.api.ApiException;
import com.sdf.api.Async;
import com.sdf.api.GroupService;
import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.layout.*;
import javafx.stage.StageStyle;
import javafx.util.Duration;

import java.util.List;

/**
 * "Available Groups" modal — lists every group the current user hasn't
 * joined yet (GroupService.list().availableGroups, i.e. GET /api/groups'
 * "available_groups"), each with its own Join button. Refetches on open
 * and every few seconds while showing, so it stays live if someone else
 * creates/joins groups elsewhere while this is up.
 */
public final class JoinGroupDialog {

    private JoinGroupDialog() {}

    /** onJoined fires after every successful join — pass whatever "refresh me" callback the calling screen needs. */
    public static void show(Runnable onJoined) {
        Dialog<Void> dialog = new Dialog<>();
        dialog.initStyle(StageStyle.TRANSPARENT);

        DialogPane pane = new DialogPane();
        pane.setStyle("-fx-background-color: transparent;");

        VBox card = new VBox(18);
        card.setMaxWidth(420);
        card.setPrefWidth(420);
        card.setPadding(new Insets(26));
        card.setStyle("-fx-background-color: white; -fx-background-radius: 16; "
                + "-fx-effect: dropshadow(gaussian, rgba(0,0,0,0.28), 28, 0, 0, 10);");

        Label title = new Label("Available Groups");
        title.setStyle("-fx-font-size: 19px; -fx-font-weight: 800; -fx-text-fill: #18181b;");

        VBox list = new VBox(10);
        Label loading = new Label("Loading\u2026");
        loading.setStyle("-fx-text-fill:#a1a1aa; -fx-font-style:italic; -fx-font-size:12px;");
        list.getChildren().add(loading);

        Button cancelLink = new Button("Cancel");
        cancelLink.setStyle("-fx-background-color: transparent; -fx-text-fill:#71717a; -fx-font-size:13px; -fx-cursor:hand;");
        cancelLink.setOnAction(e -> dialog.close());
        HBox cancelRow = new HBox(cancelLink);
        cancelRow.setAlignment(Pos.CENTER);

        card.getChildren().addAll(title, list, cancelRow);
        pane.setContent(card);
        pane.getButtonTypes().add(ButtonType.CLOSE);
        pane.lookupButton(ButtonType.CLOSE).setVisible(false);
        pane.lookupButton(ButtonType.CLOSE).setManaged(false);
        dialog.setDialogPane(pane);

        Runnable[] refresh = new Runnable[1];
        refresh[0] = () -> Async.run(
                GroupService::list,
                result -> renderList(list, result.availableGroups, refresh[0], onJoined),
                err -> list.getChildren().setAll(errorLabel("Couldn't load groups: " + friendlyMessage(err)))
        );
        refresh[0].run();

        // No websocket layer on desktop yet — poll while the dialog is open so
        // "real time" holds even if another user joins/creates groups meanwhile.
        Timeline poll = new Timeline(new KeyFrame(Duration.seconds(5), e -> refresh[0].run()));
        poll.setCycleCount(Timeline.INDEFINITE);
        poll.play();
        dialog.setOnHidden(e -> poll.stop());

        dialog.showAndWait();
    }

    private static void renderList(VBox list, List<GroupService.Group> groups, Runnable refresh, Runnable onJoined) {
        list.getChildren().clear();
        if (groups.isEmpty()) {
            Label empty = new Label("No groups available now.");
            empty.setWrapText(true);
            empty.setStyle("-fx-text-fill:#a1a1aa; -fx-font-style:italic; -fx-font-size:12.5px;");
            list.getChildren().add(empty);
            return;
        }
        for (GroupService.Group g : groups) {
            list.getChildren().add(groupRow(g, refresh, onJoined));
        }
    }

    private static HBox groupRow(GroupService.Group g, Runnable refresh, Runnable onJoined) {
        HBox row = new HBox(10);
        row.setAlignment(Pos.CENTER_LEFT);
        row.setPadding(new Insets(14));
        row.setStyle("-fx-background-color:white; -fx-border-color:#e4e4e7; -fx-border-radius:10; -fx-background-radius:10;");
        Label name = new Label(g.name);
        name.setStyle("-fx-font-size:14px; -fx-text-fill:#18181b;");
        Region sp = new Region();
        HBox.setHgrow(sp, Priority.ALWAYS);
        Button joinBtn = new Button("Join");
        joinBtn.setStyle("-fx-background-color:#2F7A54; -fx-text-fill:white; -fx-font-weight:700; -fx-font-size:12.5px; "
                + "-fx-background-radius:8; -fx-padding:7 18 7 18; -fx-cursor:hand;");
        joinBtn.setOnAction(e -> {
            joinBtn.setDisable(true);
            joinBtn.setText("Joining\u2026");
            Async.run(
                    () -> { GroupService.join(g.id); return null; },
                    v -> { onJoined.run(); refresh.run(); },
                    err -> {
                        joinBtn.setDisable(false);
                        joinBtn.setText("Join");
                        new Alert(Alert.AlertType.ERROR, friendlyMessage(err)).showAndWait();
                    }
            );
        });
        row.getChildren().addAll(name, sp, joinBtn);
        return row;
    }

    private static Label errorLabel(String msg) {
        Label l = new Label(msg);
        l.setWrapText(true);
        l.setStyle("-fx-text-fill:#dc2626; -fx-font-size:12px;");
        return l;
    }

    private static String friendlyMessage(Exception err) {
        if (err instanceof ApiException ae) return ae.firstMessage();
        return "Couldn't reach the server. Check your connection and try again.";
    }
}