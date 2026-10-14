package com.sdf;

import javafx.scene.Parent;
import javafx.scene.layout.StackPane;

import java.util.function.Supplier;

/**
 * Central navigation controller. Mirrors the prototype's showScreen(id)
 * behaviour: exactly one screen is visible in the root StackPane at a time.
 */
public final class Router {

    private static Router instance;
    private final StackPane root;

    private Router(StackPane root) {
        this.root = root;
    }

    public static void init(StackPane root) {
        instance = new Router(root);
    }

    public static Router get() {
        return instance;
    }

    /** Build and display a screen. */
    public void navigate(Supplier<Parent> screenBuilder) {
        Parent screen = screenBuilder.get();
        root.getChildren().setAll(screen);
    }
}
