package com.sdf.api;

import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.scene.Node;
import javafx.util.Duration;

/**
 * Smart Discussion Forum has no websocket layer wired up on the desktop
 * side yet (the web app uses Laravel Reverb for that). Until that's
 * built, this gives "real-time enough" behaviour: re-run `tick` on an
 * interval for as long as `owner` is still attached to the visible
 * scene graph, and stop automatically once the user navigates away
 * (Router swaps screens by removing the old root, so owner.getScene()
 * becomes null — that's our signal to stop, no manual cleanup needed).
 */
public final class LivePoller {

    private LivePoller() {}

    public static void start(Node owner, Duration interval, Runnable tick) {
        Timeline timeline = new Timeline();
        timeline.setCycleCount(Timeline.INDEFINITE);
        timeline.getKeyFrames().add(new KeyFrame(interval, e -> {
            if (owner.getScene() == null) {
                timeline.stop();
                return;
            }
            tick.run();
        }));
        timeline.play();
    }
}
