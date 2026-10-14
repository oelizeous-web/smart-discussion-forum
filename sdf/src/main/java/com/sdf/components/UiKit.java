package com.sdf.components;

import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.VBox;

/** Small factory helpers for the repeating visual patterns in the prototype. */
public final class UiKit {

    private UiKit() {}

    public static VBox statCard(String label, String value, String valueColorStyle) {
        VBox box = new VBox(10);
        box.getStyleClass().add("stat-card");
        Label l = new Label(label.toUpperCase());
        l.getStyleClass().add("stat-label");
        Label v = new Label(value);
        v.getStyleClass().add("stat-value");
        if (valueColorStyle != null) v.setStyle(valueColorStyle);
        box.getChildren().addAll(l, v);
        HBox.setHgrow(box, Priority.ALWAYS);
        return box;
    }

    public static Label badge(String text, String styleClass) {
        Label l = new Label(text);
        l.getStyleClass().add(styleClass);
        return l;
    }

    public static Button primaryButton(String text) {
        Button b = new Button(text);
        b.getStyleClass().add("btn-primary");
        return b;
    }

    public static Button outlineButton(String text) {
        Button b = new Button(text);
        b.getStyleClass().add("btn-outline");
        return b;
    }

    public static Button dangerButton(String text) {
        Button b = new Button(text);
        b.getStyleClass().add("btn-danger");
        return b;
    }

    public static VBox card() {
        VBox box = new VBox(14);
        box.getStyleClass().add("card");
        return box;
    }

    public static Label sectionTitle(String text) {
        Label l = new Label(text);
        l.getStyleClass().add("h2-section");
        return l;
    }

    public static Label pageTitle(String text) {
        Label l = new Label(text);
        l.getStyleClass().add("h1-page");
        return l;
    }

    public static Label subtitle(String text) {
        Label l = new Label(text);
        l.getStyleClass().add("subtitle");
        return l;
    }

    public static HBox spacerRow(javafx.scene.Node left, javafx.scene.Node right) {
        HBox row = new HBox();
        row.setAlignment(Pos.CENTER_LEFT);
        javafx.scene.layout.Region spacer = new javafx.scene.layout.Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);
        row.getChildren().addAll(left, spacer, right);
        return row;
    }
}
