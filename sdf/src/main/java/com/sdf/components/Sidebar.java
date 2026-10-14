package com.sdf.components;

import com.sdf.RoleRouter;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Node;
import javafx.scene.control.Label;
import javafx.scene.layout.*;

import java.util.LinkedHashMap;
import java.util.Map;

/**
 * The 240px green sidebar used on every Student / Lecturer / Admin screen:
 * brand header, nav links (one marked active), and a user profile footer.
 */
public class Sidebar extends VBox {

    private final Map<String, Runnable> navActions = new LinkedHashMap<>();

    public Sidebar(String activeLabel, String userInitial, String userName, LinkedHashMap<String, Runnable> links) {
        getStyleClass().add("sidebar");
        setPrefWidth(240);

        VBox top = new VBox();
        // Brand
        HBox brandBar = new HBox(10);
        brandBar.getStyleClass().add("sidebar-brand-bar");
        brandBar.setAlignment(Pos.CENTER_LEFT);
        Label mark = new Label("\u2318");
        mark.setStyle("-fx-text-fill: #86efac; -fx-font-size: 20px;");
        Label brand = new Label("SMART DISCUSSION");
        brand.getStyleClass().add("sidebar-brand-text");
        brandBar.getChildren().addAll(mark, brand);

        VBox nav = new VBox(6);
        nav.setPadding(new Insets(16, 14, 16, 14));

        for (Map.Entry<String, Runnable> entry : links.entrySet()) {
            String label = entry.getKey();
            Runnable action = entry.getValue();
            Label link = new Label(label);
            link.setMaxWidth(Double.MAX_VALUE);
            if (label.equals(activeLabel)) {
                link.getStyleClass().add("sidebar-link-active");
            } else {
                link.getStyleClass().add("sidebar-link");
                if (action != null) {
                    link.setOnMouseClicked(e -> action.run());
                }
            }
            nav.getChildren().add(link);
        }

        top.getChildren().addAll(brandBar, nav);

        // Footer / profile
        HBox footer = new HBox(10);
        footer.getStyleClass().add("sidebar-footer");
        footer.setAlignment(Pos.CENTER_LEFT);

        StackPane avatar = new StackPane();
        avatar.getStyleClass().add("avatar-circle");
        Label avatarText = new Label(userInitial);
        avatarText.getStyleClass().add("avatar-text");
        avatar.getChildren().add(avatarText);

        VBox userBox = new VBox(1);
        Label name = new Label(userName);
        name.getStyleClass().add("sidebar-user-name");
        Label logout = new Label("Log out");
        logout.getStyleClass().add("sidebar-user-role");
        logout.setStyle("-fx-cursor: hand; -fx-underline: true;");
        logout.setOnMouseClicked(e -> RoleRouter.logout());
        userBox.getChildren().addAll(name, logout);

        footer.getChildren().addAll(avatar, userBox);

        VBox.setVgrow(top, Priority.ALWAYS);
        BorderPane wrapper = new BorderPane();
        wrapper.setTop(top);
        wrapper.setBottom(footer);
        wrapper.setPrefWidth(240);
        wrapper.getStyleClass().add("sidebar");

        getChildren().add(wrapper);
        VBox.setVgrow(wrapper, Priority.ALWAYS);
    }
}
