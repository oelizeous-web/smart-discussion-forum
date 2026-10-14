package com.sdf.api;

import javafx.application.Platform;

import java.util.concurrent.Callable;
import java.util.function.Consumer;

public final class Async {

    private Async() {}

    public static <T> void run(Callable<T> work, Consumer<T> onSuccess, Consumer<Exception> onError) {
        Thread t = new Thread(() -> {
            try {
                T result = work.call();
                Platform.runLater(() -> onSuccess.accept(result));
            } catch (Exception e) {
                Platform.runLater(() -> onError.accept(e));
            }
        });
        t.setDaemon(true);
        t.start();
    }
}
