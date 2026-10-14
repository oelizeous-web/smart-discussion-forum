package com.sdf.screens.admin;

import com.sdf.Router;
import com.sdf.components.PlatformRules;
import com.sdf.components.Sidebar;
import com.sdf.components.UiKit;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.control.Alert;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.TextArea;
import javafx.scene.control.TextField;
import javafx.scene.layout.BorderPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.VBox;

import java.util.LinkedHashMap;

/**
 * NOTE: the JSON API doc you supplied has no endpoint to persist any of
 * these settings server-side (no PUT /api/admin/settings or similar),
 * so "Save Configurations" is intentionally honest about that instead
 * of pretending to save. If your backend gains a settings endpoint,
 * this is the one screen that needs a real Async.run() wired in.
 */
public class AdminSettingsScreen {

    public static Parent build() {
        BorderPane root = new BorderPane();
        root.getStyleClass().add("app-shell");

        LinkedHashMap<String, Runnable> links = new LinkedHashMap<>();
        links.put("Dashboard", () -> Router.get().navigate(AdminDashboardScreen::build));
        links.put("Members", () -> Router.get().navigate(AdminMembersScreen::build));
        links.put("Settings", null);
        links.put("Reports", () -> Router.get().navigate(AdminReportsScreen::build));
        root.setLeft(new Sidebar("Settings", com.sdf.session.Session.get().getInitial(), com.sdf.session.Session.get().getName(), links));

        VBox content = new VBox(20);
        content.getStyleClass().add("bg-gray-page");
        content.setPadding(new Insets(28));

        Label title = new Label("System Settings");
        title.setStyle("-fx-font-size: 20px; -fx-font-weight: 800; -fx-text-fill: #111827;");
        Label sub = new Label("Configure automated enforcement thresholds and platform onboarding rules.");
        sub.getStyleClass().add("muted");

        HBox row = new HBox(20);

        VBox enforcementBox = UiKit.card();
        HBox.setHgrow(enforcementBox, Priority.ALWAYS);
        Label enforcementTitle = new Label("Automation & Enforcement");
        enforcementTitle.setStyle("-fx-font-size: 14px; -fx-font-weight: 800; -fx-text-fill: #111827;");

        Label inactivityLabel = new Label("Inactivity Threshold (Days)");
        inactivityLabel.getStyleClass().add("field-label");
        TextField inactivity = new TextField("7");
        Label inactivityHint = new Label("The duration a user must be inactive before the system issues automated warnings.");
        inactivityHint.setWrapText(true);
        inactivityHint.setStyle("-fx-font-size: 10.5px; -fx-text-fill: #6b7280;");

        Label blacklistLabel = new Label("Blacklist Duration (Days)");
        blacklistLabel.getStyleClass().add("field-label");
        TextField blacklist = new TextField("14");
        Label blacklistHint = new Label("How long a member is automatically blacklisted after failing to comply with warnings.");
        blacklistHint.setWrapText(true);
        blacklistHint.setStyle("-fx-font-size: 10.5px; -fx-text-fill: #6b7280;");

        enforcementBox.getChildren().addAll(
                enforcementTitle,
                new VBox(4, inactivityLabel, inactivity, inactivityHint),
                new VBox(4, blacklistLabel, blacklist, blacklistHint)
        );

        VBox onboardingBox = UiKit.card();
        HBox.setHgrow(onboardingBox, Priority.ALWAYS);
        Label onboardingTitle = new Label("Onboarding & Registration");
        onboardingTitle.setStyle("-fx-font-size: 14px; -fx-font-weight: 800; -fx-text-fill: #111827;");
        Label rulesLabel = new Label("Platform Rules & Guidelines");
        rulesLabel.getStyleClass().add("field-label");
        TextArea rules = new TextArea(PlatformRules.asPlainText());
        rules.setPrefRowCount(8);
        Label rulesHint = new Label("Shown to every new student at registration (they must agree before their account is created).");
        rulesHint.setWrapText(true);
        rulesHint.setStyle("-fx-font-size: 10.5px; -fx-text-fill: #6b7280;");
        onboardingBox.getChildren().addAll(onboardingTitle, rulesLabel, rules, rulesHint);

        row.getChildren().addAll(enforcementBox, onboardingBox);

        Label persistNote = new Label("These settings aren't persisted to the server yet \u2014 your API doesn't expose a settings endpoint.");
        persistNote.setWrapText(true);
        persistNote.setStyle("-fx-font-size: 11px; -fx-text-fill: #a1a1aa; -fx-font-style: italic;");

        Button save = new Button("Save Configurations");
        save.getStyleClass().add("btn-primary");
        save.setOnAction(e -> new Alert(Alert.AlertType.INFORMATION,
                "There's no backend endpoint yet to save these settings, so nothing was sent to the server. "
                        + "Once your API adds one (e.g. PUT /api/admin/settings), this button is ready to wire up.").showAndWait());
        HBox saveRow = new HBox(10, persistNote, save);
        saveRow.setAlignment(Pos.CENTER_RIGHT);

        content.getChildren().addAll(title, sub, row, saveRow);
        root.setCenter(content);
        return root;
    }
}
