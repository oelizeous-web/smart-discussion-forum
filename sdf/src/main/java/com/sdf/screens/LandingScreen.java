package com.sdf.screens;

import com.sdf.AppInfo;
import com.sdf.Router;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.ScrollPane;
import javafx.scene.layout.*;

public class LandingScreen {

    public static Parent build() {
        BorderPane root = new BorderPane();
        root.getStyleClass().add("bg-cream");

        // ---- Header ----
        HBox header = new HBox();
        header.getStyleClass().add("public-header");
        header.setAlignment(Pos.CENTER_LEFT);

        HBox brand = new HBox(10);
        brand.setAlignment(Pos.CENTER_LEFT);
        StackPane logoBox = new StackPane();
        logoBox.getStyleClass().add("brand-logo-box");
        Label logoText = new Label("S");
        logoText.getStyleClass().add("brand-logo-text");
        logoBox.getChildren().add(logoText);
        Label brandName = new Label(AppInfo.NAME);
        brandName.getStyleClass().add("brand-name");
        brand.getChildren().addAll(logoBox, brandName);

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        Button loginBtn = new Button("Log in");
        loginBtn.getStyleClass().add("btn-secondary");
        loginBtn.setOnAction(e -> Router.get().navigate(LoginScreen::build));

        HBox headerActions = new HBox(12, loginBtn);
        header.getChildren().addAll(brand, spacer, headerActions);

        // ---- Hero ----
        VBox hero = new VBox(18);
        hero.setAlignment(Pos.CENTER);
        hero.setPadding(new Insets(70, 40, 60, 40));

        Label eyebrow = new Label("\u25CF  ACADEMIC COMMUNITY PLATFORM");
        eyebrow.getStyleClass().add("pill-eyebrow");

        Label title = new Label("Welcome to the\n" + AppInfo.NAME);
        title.getStyleClass().addAll("h1-hero", "font-serif-display");
        title.setStyle("-fx-text-alignment: center;");
        title.setTextAlignment(javafx.scene.text.TextAlignment.CENTER);

        Label titleAccent = new Label(AppInfo.NAME);
        titleAccent.setStyle("-fx-text-fill: #2F7A54; -fx-font-size: 42px; -fx-font-weight: 700;");

        VBox titleBox = new VBox(0);
        titleBox.setAlignment(Pos.CENTER);
        Label line1 = new Label("Welcome to the");
        line1.getStyleClass().addAll("h1-hero", "font-serif-display");
        titleBox.getChildren().addAll(line1, titleAccent);

        Label desc = new Label("Share, learn, and grow together. Join our academic community to engage in\nmeaningful discussions, access valuable resources, and connect with peers and lecturers.");
        desc.getStyleClass().add("muted-cream");
        desc.setStyle("-fx-font-size: 15px;");
        desc.setTextAlignment(javafx.scene.text.TextAlignment.CENTER);
        desc.setWrapText(true);
        desc.setMaxWidth(560);

        Button getStarted = new Button("Get Started");
        getStarted.getStyleClass().add("btn-primary");
        getStarted.setPrefWidth(220);
        getStarted.setStyle("-fx-font-size: 14px; -fx-padding: 12 24 12 24;");
        getStarted.setOnAction(e -> Router.get().navigate(RegisterScreen::build));

        hero.getChildren().addAll(eyebrow, titleBox, desc, getStarted);

        // ---- Feature cards ----
        HBox features = new HBox(20);
        features.setPadding(new Insets(10, 60, 60, 60));
        features.setAlignment(Pos.CENTER);

        features.getChildren().addAll(
                featureCard("Academic Discussions", "Engage in organized topics strictly moderated to ensure quality learning and continuous academic growth."),
                featureCard("Connect with Lecturers", "Get direct insights, feedback, and guidance from authorized university staff members and professors."),
                featureCard("Secure & Moderated", "A safe platform equipped with clear community rules, ensuring a respectful environment for all members.")
        );

        VBox center = new VBox(hero, features);
        ScrollPane scroll = new ScrollPane(center);
        scroll.setFitToWidth(true);
        scroll.getStyleClass().add("scroll-pane");

        // ---- Footer ----
        Label footer = new Label("\u00A9 2026 " + AppInfo.NAME + ". All rights reserved.");
        footer.getStyleClass().add("muted-cream");
        HBox footerBar = new HBox(footer);
        footerBar.setAlignment(Pos.CENTER);
        footerBar.setPadding(new Insets(18));
        footerBar.setStyle("-fx-background-color: #F1EEE4; -fx-border-color: #E4E0D3 transparent transparent transparent;-fx-border-width:1 0 0 0;");

        VBox page = new VBox(header, scroll, footerBar);
        VBox.setVgrow(scroll, Priority.ALWAYS);

        root.setCenter(page);
        return root;
    }

    private static VBox featureCard(String title, String desc) {
        VBox card = new VBox(14);
        card.getStyleClass().add("card-cream");
        card.setPrefWidth(320);
        card.setMaxWidth(320);

        StackPane iconBox = new StackPane();
        iconBox.setStyle("-fx-background-color: rgba(92,201,139,0.14); -fx-background-radius: 10; -fx-min-width:44; -fx-min-height:44; -fx-max-width:44; -fx-max-height:44;");
        Label icon = new Label("\u2713");
        icon.setStyle("-fx-text-fill: #2F7A54; -fx-font-size: 18px; -fx-font-weight: 700;");
        iconBox.getChildren().add(icon);

        Label t = new Label(title);
        t.setStyle("-fx-font-size: 16px; -fx-font-weight: 700; -fx-text-fill: #232A22;");
        Label d = new Label(desc);
        d.getStyleClass().add("muted-cream");
        d.setWrapText(true);
        d.setStyle("-fx-font-size: 12.5px;");

        card.getChildren().addAll(iconBox, t, d);
        return card;
    }
}
