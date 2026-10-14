package com.sdf;

import com.sdf.database.DatabaseConnection;
import com.sdf.screens.LoginScreen;
import com.sdf.service.SyncService;
import javafx.application.Application;
import javafx.scene.Scene;
import javafx.scene.image.Image;
import javafx.scene.layout.StackPane;
import javafx.stage.Stage;

/**
 * Smart Discussion Forum — Desktop Application.
 */
public class MainApp extends Application {

    @Override
    public void start(Stage stage) {
        DatabaseConnection.initializeDatabase();

        SyncService.startAutomaticSync();

        StackPane root = new StackPane();
        Router.init(root);

        Scene scene =
                new Scene(
                        root,
                        1200,
                        600
                );

        scene.getStylesheets().add(
                getClass()
                        .getResource("/css/app.css")
                        .toExternalForm()
        );

        stage.setTitle(AppInfo.NAME);
        var iconStream = getClass().getResourceAsStream("/images/app-icon.png.png");
        if (iconStream != null) {
            stage.getIcons().add(new Image(iconStream));
        }
        stage.setScene(scene);
        stage.setMinWidth(1200);
        stage.setMinHeight(600);
        stage.show();

        Router.get().navigate(
                LoginScreen::build
        );
    }

    @Override
    public void stop() {
        SyncService.stopAutomaticSync();
    }

    public static void main(String[] args) {
        launch(args);
    }
}