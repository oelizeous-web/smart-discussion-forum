package com.sdf.screens;

import com.sdf.AppInfo;
import com.sdf.RoleRouter;
import com.sdf.Router;
import com.sdf.api.ApiException;
import com.sdf.api.Async;
import com.sdf.api.AuthService;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.*;

public class RegisterScreen {

    public static Parent build() {
        VBox root = new VBox(0);
        root.getStyleClass().add("bg-gray-page");
        root.setAlignment(Pos.TOP_CENTER);
        root.setPadding(new Insets(40, 20, 40, 20));

        VBox card = new VBox(0);
        card.setMaxWidth(440);
        card.setPrefWidth(440);
        card.setStyle("-fx-background-color: white; -fx-border-color: #e4e4e7; -fx-border-radius: 16; -fx-background-radius: 16; -fx-effect: dropshadow(gaussian, rgba(0,0,0,0.12), 14, 0, 0, 4);");

        // top strip
        HBox topStrip = new HBox(8);
        topStrip.setAlignment(Pos.CENTER_LEFT);
        topStrip.setStyle("-fx-background-color: #2F7A54; -fx-background-radius: 16 16 0 0; -fx-padding: 12 18 12 18;");
        Label stripLabel = new Label(AppInfo.NAME);
        stripLabel.setStyle("-fx-text-fill: white; -fx-font-size: 12px; -fx-font-weight: 700;");
        topStrip.getChildren().add(stripLabel);

        // header block
        VBox headerBlock = new VBox(8);
        headerBlock.setAlignment(Pos.CENTER);
        headerBlock.setStyle("-fx-background-color: #fafafa; -fx-border-color: transparent transparent #e4e4e7 transparent; -fx-border-width: 0 0 1 0;");
        headerBlock.setPadding(new Insets(28, 24, 22, 24));

        StackPane icon = new StackPane();
        icon.setStyle("-fx-background-color:#5CC98B; -fx-background-radius:16; -fx-min-width:56; -fx-min-height:56; -fx-max-width:56; -fx-max-height:56;");
        Label iconGlyph = new Label("\u26A1");
        iconGlyph.setStyle("-fx-font-size: 22px;");
        icon.getChildren().add(iconGlyph);

        Label h1 = new Label("Create an account");
        h1.setStyle("-fx-font-size: 18px; -fx-font-weight: 800; -fx-text-fill: #09090b;");
        Label sub = new Label("Join " + AppInfo.NAME);
        sub.setStyle("-fx-font-size: 11px; -fx-text-fill: #71717a; -fx-font-weight: 600;");

        headerBlock.getChildren().addAll(icon, h1, sub);

        // form
        VBox form = new VBox(14);
        form.setPadding(new Insets(24));

        TextField name = new TextField();
        TextField email = new TextField();
        PasswordField pass = new PasswordField();
        PasswordField confirm = new PasswordField();

        CheckBox agree = new CheckBox();
        Label agreeLabelPrefix = new Label("I agree to the");
        agreeLabelPrefix.setStyle("-fx-font-size: 11px; -fx-text-fill: #3f3f46; -fx-font-weight: 600;");
        Hyperlink platformRulesLink = new Hyperlink("platform rules");
        platformRulesLink.getStyleClass().add("link-brand");
        platformRulesLink.setStyle("-fx-font-size: 11px; -fx-font-weight: 700;");
        platformRulesLink.setOnAction(e -> com.sdf.components.PlatformRulesDialog.show());
        Label agreeLabelSuffix = new Label("and terms");
        agreeLabelSuffix.setStyle("-fx-font-size: 11px; -fx-text-fill: #3f3f46; -fx-font-weight: 600;");
        HBox agreeRow = new HBox(4, agree, agreeLabelPrefix, platformRulesLink, agreeLabelSuffix);
        agreeRow.setAlignment(Pos.CENTER_LEFT);

        Label errorLabel = new Label();
        errorLabel.setStyle("-fx-text-fill: #dc2626; -fx-font-size: 11px; -fx-font-weight: 600; -fx-wrap-text: true;");
        errorLabel.setManaged(false);
        errorLabel.setVisible(false);

        Button registerBtn = new Button("Register  \u2192");
        registerBtn.getStyleClass().add("btn-primary");
        registerBtn.setMaxWidth(Double.MAX_VALUE);
        registerBtn.setStyle("-fx-font-size: 13px; -fx-padding: 12;");
        registerBtn.setOnAction(e -> {
            errorLabel.setManaged(false);
            errorLabel.setVisible(false);

            String nameValue = name.getText().trim();
            String emailValue = email.getText().trim();
            String passValue = pass.getText();
            String confirmValue = confirm.getText();

            if (nameValue.isEmpty() || emailValue.isEmpty() || passValue.isEmpty()) {
                showError(errorLabel, "Please fill in all fields.");
                return;
            }
            if (!passValue.equals(confirmValue)) {
                showError(errorLabel, "Passwords do not match.");
                return;
            }
            if (passValue.length() < 8) {
                showError(errorLabel, "Password must be at least 8 characters.");
                return;
            }
            if (!agree.isSelected()) {
                showError(errorLabel, "You must agree to the platform rules.");
                return;
            }

            registerBtn.setDisable(true);
            registerBtn.setText("Creating account\u2026");

            Async.run(
                    () -> AuthService.register(nameValue, emailValue, passValue, confirmValue, true),
                    session -> {
                        registerBtn.setDisable(false);
                        registerBtn.setText("Register  \u2192");
                        RoleRouter.navigateToDashboard();
                    },
                    err -> {
                        registerBtn.setDisable(false);
                        registerBtn.setText("Register  \u2192");
                        String msg = (err instanceof ApiException ae)
                                ? ae.firstMessage()
                                : "Couldn't reach the server. Check your connection and try again.";
                        showError(errorLabel, msg);
                    }
            );
        });

        Label already = new Label("Already have an account?");
        already.setStyle("-fx-font-size: 11px; -fx-text-fill: #71717a;");
        Hyperlink loginLink = new Hyperlink("Login here");
        loginLink.getStyleClass().add("link-brand");
        loginLink.setOnAction(e -> Router.get().navigate(LoginScreen::build));
        HBox loginRow = new HBox(4, already, loginLink);
        loginRow.setAlignment(Pos.CENTER);

        form.getChildren().addAll(
                fieldGroup("Full Name", name),
                fieldGroup("Email Address", email),
                fieldGroup("Password", pass),
                fieldGroup("Confirm Password", confirm),
                agreeRow,
                errorLabel,
                registerBtn,
                loginRow
        );

        card.getChildren().addAll(topStrip, headerBlock, form);
        root.getChildren().add(card);
        return root;
    }

    private static VBox fieldGroup(String labelText, Control field) {
        Label l = new Label(labelText);
        l.getStyleClass().add("field-label");
        VBox box = new VBox(4, l, field);
        return box;
    }

    private static void showError(Label errorLabel, String message) {
        errorLabel.setText(message);
        errorLabel.setManaged(true);
        errorLabel.setVisible(true);
    }
}
