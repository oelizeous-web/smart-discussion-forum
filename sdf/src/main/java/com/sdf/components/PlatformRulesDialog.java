package com.sdf.components;

import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.Button;
import javafx.scene.control.Dialog;
import javafx.scene.control.DialogPane;
import javafx.scene.control.Label;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Region;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import javafx.stage.StageStyle;

public final class PlatformRulesDialog {

    private PlatformRulesDialog() {}

    public static void show() {
        Dialog<Void> dialog = new Dialog<>();
        dialog.initStyle(StageStyle.TRANSPARENT);

        DialogPane pane = new DialogPane();
        pane.setStyle("-fx-background-color: transparent;");

        VBox card = new VBox(18);
        card.setMaxWidth(420);
        card.setPrefWidth(420);
        card.setPadding(new Insets(26));
        card.setStyle("-fx-background-color: white; -fx-background-radius: 16; "
                + "-fx-effect: dropshadow(gaussian, rgba(0,0,0,0.25), 24, 0, 0, 8);");

        HBox headerRow = new HBox(14);
        headerRow.setAlignment(Pos.CENTER_LEFT);

        StackPane icon = new StackPane();
        icon.setStyle("-fx-background-color:#5CC98B; -fx-background-radius:12; "
                + "-fx-min-width:40; -fx-min-height:40; -fx-max-width:40; -fx-max-height:40;");
        Label iconGlyph = new Label("\uD83D\uDEE1");
        iconGlyph.setStyle("-fx-font-size: 17px;");
        icon.getChildren().add(iconGlyph);

        Label title = new Label("Platform Rules");
        title.setStyle("-fx-font-size: 18px; -fx-font-weight: 800; -fx-text-fill: #09090b;");

        Region spacer = new Region();
        HBox.setHgrow(spacer, javafx.scene.layout.Priority.ALWAYS);

        Button closeX = new Button("\u2715");
        closeX.setStyle("-fx-background-color: transparent; -fx-text-fill: #a1a1aa; -fx-font-size: 14px; -fx-cursor: hand;");
        closeX.setOnAction(e -> dialog.close());

        headerRow.getChildren().addAll(icon, title, spacer, closeX);

        VBox rulesList = new VBox(12);
        for (String rule : PlatformRules.RULES) {
            HBox row = new HBox(10);
            row.setAlignment(Pos.TOP_LEFT);
            Label bullet = new Label("\u2022");
            bullet.setStyle("-fx-text-fill: #2F7A54; -fx-font-weight: 800; -fx-font-size: 14px;");
            Label text = new Label(rule);
            text.setWrapText(true);
            text.setStyle("-fx-font-size: 13px; -fx-text-fill: #27272a;");
            row.getChildren().addAll(bullet, text);
            rulesList.getChildren().add(row);
        }

        Button closeBtn = new Button("Close");
        closeBtn.setMaxWidth(Double.MAX_VALUE);
        closeBtn.setStyle("-fx-background-color: white; -fx-text-fill:#2F7A54; -fx-font-weight:700; "
                + "-fx-border-color:#2F7A54; -fx-border-radius:10; -fx-background-radius:10; -fx-padding:10; -fx-cursor: hand;");
        closeBtn.setOnAction(e -> dialog.close());

        card.getChildren().addAll(headerRow, rulesList, closeBtn);
        pane.setContent(card);
        pane.getButtonTypes().add(javafx.scene.control.ButtonType.CLOSE);
        pane.lookupButton(javafx.scene.control.ButtonType.CLOSE).setVisible(false);
        pane.lookupButton(javafx.scene.control.ButtonType.CLOSE).setManaged(false);

        dialog.setDialogPane(pane);
        dialog.showAndWait();
    }
}
