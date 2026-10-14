package com.sdf.screens;

import com.sdf.AppInfo;
import com.sdf.RoleRouter;
import com.sdf.Router;
import com.sdf.api.ApiException;
import com.sdf.api.Async;
import com.sdf.api.AuthService;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Cursor;
import javafx.scene.Parent;
import javafx.scene.control.Button;
import javafx.scene.control.CheckBox;
import javafx.scene.control.Hyperlink;
import javafx.scene.control.Label;
import javafx.scene.control.PasswordField;
import javafx.scene.control.TextField;
import javafx.scene.layout.BorderPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import javafx.scene.paint.Color;
import javafx.scene.shape.SVGPath;
import javafx.scene.text.Font;
import javafx.scene.text.FontWeight;
import javafx.scene.text.Text;
import javafx.scene.text.TextAlignment;
import javafx.scene.text.TextFlow;

public class LoginScreen {

    private static final String PAPER = "#F7F5EE";
    private static final String BRAND_DEEP = "#2F7A54";
    private static final String BRAND_DEEP_H = "#256242";
    private static final String INK = "#232A22";
    private static final String INK_SOFT = "#5B6255";
    private static final String HAIRLINE = "#E4E0D3";

    public static Parent build() {
        BorderPane root = new BorderPane();
        root.setStyle("-fx-background-color: " + PAPER + ";");
        root.setTop(buildHeader());

        HBox centerSplit = new HBox();
        VBox leftPanel = buildLeftPanel();
        VBox rightPanel = buildRightPanel();

        HBox.setHgrow(leftPanel, Priority.ALWAYS);
        HBox.setHgrow(rightPanel, Priority.ALWAYS);
        leftPanel.prefWidthProperty().bind(centerSplit.widthProperty().divide(2));
        rightPanel.prefWidthProperty().bind(centerSplit.widthProperty().divide(2));

        centerSplit.getChildren().addAll(leftPanel, rightPanel);
        root.setCenter(centerSplit);
        root.setBottom(buildFooter());

        return root;
    }

    private static HBox buildHeader() {
        HBox header = new HBox();
        header.setAlignment(Pos.CENTER_LEFT);
        header.setPadding(new Insets(14, 40, 14, 40));
        header.setStyle("-fx-background-color: " + PAPER + ";"
                + "-fx-border-color: transparent transparent " + HAIRLINE + " transparent;"
                + "-fx-border-width: 0 0 1 0;");

        Region logoBadge = new Region();
        logoBadge.setPrefSize(34, 34);
        logoBadge.setMaxSize(34, 34);
        logoBadge.setStyle("-fx-background-color: " + BRAND_DEEP + "; -fx-background-radius: 8;");

        SVGPath logoIcon = new SVGPath();
        logoIcon.setContent("M12 4L3 8L12 12L21 8L12 4Z M6 10.5V16C6 16 8.5 18 12 18C15.5 18 18 16 18 16V10.5 M21 8V14");
        logoIcon.setStroke(Color.WHITE);
        logoIcon.setStrokeWidth(1.7);
        logoIcon.setFill(Color.TRANSPARENT);
        logoIcon.setScaleX(0.75);
        logoIcon.setScaleY(0.75);

        StackPane logoStack = new StackPane(logoBadge, logoIcon);

        Label title = new Label(AppInfo.NAME);
        title.setFont(Font.font("Segoe UI", FontWeight.BOLD, 16));
        title.setTextFill(Color.web(INK));

        HBox left = new HBox(12, logoStack, title);
        left.setAlignment(Pos.CENTER_LEFT);

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        header.getChildren().addAll(left, spacer);
        return header;
    }

    private static VBox buildLeftPanel() {
        VBox panel = new VBox();
        panel.setAlignment(Pos.CENTER);
        panel.setPadding(new Insets(40));
        panel.setStyle("-fx-background-color: " + BRAND_DEEP + ";");

        Text line1 = new Text("Welcome Back to the\n");
        line1.setFont(Font.font("Georgia", FontWeight.NORMAL, 36));
        line1.setFill(Color.WHITE);

        Text line2 = new Text(AppInfo.NAME);
        line2.setFont(Font.font("Georgia", FontWeight.BOLD, 38));
        line2.setFill(Color.WHITE);

        TextFlow heading = new TextFlow(line1, line2);
        heading.setTextAlignment(TextAlignment.CENTER);
        heading.setMaxWidth(480);

        panel.getChildren().add(heading);
        return panel;
    }

    private static VBox buildRightPanel() {
        VBox panel = new VBox(16);
        panel.setAlignment(Pos.CENTER);
        panel.setPadding(new Insets(40));
        panel.setStyle("-fx-background-color: " + PAPER + ";");

        VBox formContainer = new VBox(14);
        formContainer.setMaxWidth(360);
        formContainer.setAlignment(Pos.CENTER_LEFT);

        Label heading = new Label("Sign In to Your Account");
        heading.setFont(Font.font("Georgia", FontWeight.BOLD, 25));
        heading.setTextFill(Color.web(INK));
        heading.setPadding(new Insets(0, 0, 10, 0));

        Label emailLabel = new Label("Email Address");
        emailLabel.setFont(Font.font("Segoe UI", FontWeight.SEMI_BOLD, 13));
        emailLabel.setTextFill(Color.web(INK));

        TextField emailField = new TextField();
        emailField.setPromptText("you@university.edu");
        emailField.setPrefHeight(42);
        emailField.getStyleClass().add("auth-field");

        Label passwordLabel = new Label("Password");
        passwordLabel.setFont(Font.font("Segoe UI", FontWeight.SEMI_BOLD, 13));
        passwordLabel.setTextFill(Color.web(INK));

        PasswordField passwordField = new PasswordField();
        passwordField.setPromptText("••••••••••••");
        passwordField.setPrefHeight(42);
        passwordField.getStyleClass().add("auth-field");

        CheckBox rememberMe = new CheckBox("Remember me");
        rememberMe.setStyle("-fx-text-fill: " + INK_SOFT + ";");

        Hyperlink forgotLink = new Hyperlink("Forgot Password?");
        forgotLink.setFont(Font.font("Segoe UI", 12));
        forgotLink.setTextFill(Color.web(BRAND_DEEP));
        forgotLink.setBorder(null);
        forgotLink.setCursor(Cursor.HAND);
        forgotLink.setOnAction(e -> Router.get().navigate(ForgotPasswordScreen::build));

        HBox optionsRow = new HBox();
        optionsRow.setAlignment(Pos.CENTER_LEFT);
        optionsRow.getChildren().addAll(rememberMe);

        Label errorLabel = new Label();
        errorLabel.setStyle("-fx-text-fill: #dc2626; -fx-font-size: 12px; -fx-font-weight: 600; -fx-wrap-text: true;");
        errorLabel.setManaged(false);
        errorLabel.setVisible(false);

        Button loginBtn = createPillButton("Log in", true);
        loginBtn.setMaxWidth(Double.MAX_VALUE);
        loginBtn.setPrefHeight(42);
        loginBtn.setOnAction(e -> {
            errorLabel.setManaged(false);
            errorLabel.setVisible(false);

            String emailValue = emailField.getText().trim();
            String passwordValue = passwordField.getText();
            if (emailValue.isEmpty() || passwordValue.isEmpty()) {
                showError(errorLabel, "Please enter your email and password.");
                return;
            }

            loginBtn.setDisable(true);
            loginBtn.setText("Logging in…");

            Async.run(
                    () -> AuthService.login(emailValue, passwordValue),
                    session -> {
                        loginBtn.setDisable(false);
                        loginBtn.setText("Log in");
                        RoleRouter.navigateToDashboard();
                    },
                    err -> {
                        loginBtn.setDisable(false);
                        loginBtn.setText("Log in");
                        String msg = (err instanceof ApiException ae)
                                ? (ae.isSuspended() ? "Your account has been suspended." : ae.firstMessage())
                                : "Couldn't reach the server. Check your connection and try again.";
                        showError(errorLabel, msg);
                    }
            );
        });

        Label noAccount = new Label("Don't have an account?");
        noAccount.setFont(Font.font("Segoe UI", 13));
        noAccount.setTextFill(Color.web(INK_SOFT));

        Hyperlink registerLink = new Hyperlink("Register");
        registerLink.setFont(Font.font("Segoe UI", FontWeight.BOLD, 13));
        registerLink.setTextFill(Color.web(BRAND_DEEP));
        registerLink.setBorder(null);
        registerLink.setCursor(Cursor.HAND);
        registerLink.setOnAction(e -> Router.get().navigate(RegisterScreen::build));

        HBox registerRow = new HBox(4, noAccount, registerLink);
        registerRow.setAlignment(Pos.CENTER);
        registerRow.setPadding(new Insets(10, 0, 0, 0));

        formContainer.getChildren().addAll(
                heading,
                emailLabel, emailField,
                passwordLabel, passwordField,
                optionsRow,
                errorLabel,
                loginBtn,
                forgotLink,
                registerRow
        );

        panel.getChildren().add(formContainer);
        return panel;
    }

    private static HBox buildFooter() {
        HBox footer = new HBox();
        footer.setAlignment(Pos.CENTER);
        footer.setPadding(new Insets(14));
        footer.setStyle("-fx-background-color: " + PAPER + ";"
                + "-fx-border-color: " + HAIRLINE + " transparent transparent transparent;"
                + "-fx-border-width: 1 0 0 0;");

        Label copyright = new Label("© 2026 " + AppInfo.NAME + ". All rights reserved.");
        copyright.setFont(Font.font("Segoe UI", 12));
        copyright.setTextFill(Color.web(INK_SOFT));

        footer.getChildren().add(copyright);
        return footer;
    }

    private static Button createPillButton(String text, boolean filled) {
        Button btn = new Button(text);
        btn.setCursor(Cursor.HAND);
        btn.setFont(Font.font("Segoe UI", FontWeight.SEMI_BOLD, 13));
        btn.setPadding(new Insets(8, 22, 8, 22));

        String base = "-fx-background-radius: 20; -fx-border-radius: 20;";
        if (filled) {
            btn.setStyle(base + "-fx-background-color: " + BRAND_DEEP + "; -fx-text-fill: white;");
            btn.setOnMouseEntered(e -> btn.setStyle(base + "-fx-background-color: " + BRAND_DEEP_H + "; -fx-text-fill: white;"));
            btn.setOnMouseExited(e -> btn.setStyle(base + "-fx-background-color: " + BRAND_DEEP + "; -fx-text-fill: white;"));
        }
        return btn;
    }

    private static void showError(Label errorLabel, String message) {
        errorLabel.setText(message);
        errorLabel.setManaged(true);
        errorLabel.setVisible(true);
    }
}
