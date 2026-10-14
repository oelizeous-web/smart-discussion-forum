package com.sdf.screens;

import com.sdf.Router;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.layout.VBox;
import javafx.scene.text.TextAlignment;

public class ForgotPasswordScreen {

    public static Parent build() {
        VBox root = new VBox(20);
        root.setAlignment(Pos.CENTER);
        root.setPadding(new Insets(40));
        root.getStyleClass().add("bg-cream");

        VBox card = new VBox(18);
        card.getStyleClass().add("card");
        card.setMaxWidth(500);
        card.setAlignment(Pos.CENTER);
        card.setPadding(new Insets(28));

        Label title = new Label("Reset your password");
        title.getStyleClass().addAll("h1-page");

        Label message = new Label(
                "Please contact your lecturer or administrator to reset access for your account.\n"
                        + "If you are already registered, make sure you use the email linked to your university account."
        );
        message.setWrapText(true);
        message.setTextAlignment(TextAlignment.CENTER);
        message.setMaxWidth(420);
        message.getStyleClass().add("muted");

        Button backButton = new Button("Back to login");
        backButton.getStyleClass().add("btn-primary");
        backButton.setOnAction(e -> Router.get().navigate(LoginScreen::build));

        card.getChildren().addAll(title, message, backButton);
        root.getChildren().add(card);
        return root;
    }
}
