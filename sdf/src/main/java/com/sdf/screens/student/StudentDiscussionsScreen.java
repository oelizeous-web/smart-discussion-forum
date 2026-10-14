package com.sdf.screens.student;

import com.sdf.Router;
import com.sdf.api.ApiException;
import com.sdf.api.Async;
import com.sdf.api.GroupService;
import com.sdf.api.LivePoller;
import com.sdf.api.PostService;
import com.sdf.api.TopicService;
import com.sdf.components.CreateGroupDialog;
import com.sdf.components.CreateTopicDialog;
import com.sdf.components.JoinGroupDialog;
import com.sdf.components.Sidebar;
import javafx.application.Platform;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Node;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.*;
import javafx.util.Duration;

import java.util.HashSet;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Set;
import java.util.function.LongConsumer;

public class StudentDiscussionsScreen {

    /** Entry from the sidebar nav link — no group preselected: show the Welcome state. */
    public static Parent build() {
        return build(-1, null, -1, -1);
    }

    /** Entry from the Dashboard — a specific group (and maybe topic) was clicked. */
    public static Parent build(long presetGroupId, String presetGroupName, long presetTopicId) {
        return build(presetGroupId, presetGroupName, presetTopicId, -1);
    }

    /** Entry from a Notification click — group, topic, and the specific reply to highlight. */
    public static Parent build(long presetGroupId, String presetGroupName, long presetTopicId, long presetPostId) {
        BorderPane root = new BorderPane();
        root.getStyleClass().add("app-shell");

        LinkedHashMap<String, Runnable> links = new LinkedHashMap<>();
        links.put("Dashboard", () -> Router.get().navigate(StudentDashboardScreen::build));
        links.put("Discussions", null);
        links.put("Quizzes", () -> Router.get().navigate(StudentQuizzesScreen::build));
        links.put("Notifications", () -> Router.get().navigate(NotificationsScreen::build));
        root.setLeft(new Sidebar("Discussions", com.sdf.session.Session.get().getInitial(), com.sdf.session.Session.get().getName(), links));

        // ---- groups & topics column (left of the chat) ----
        VBox groupsCol = new VBox();
        groupsCol.setPrefWidth(300);
        groupsCol.setStyle("-fx-background-color: white; -fx-border-color: transparent #e4e4e7 transparent transparent; -fx-border-width: 0 1 0 0;");

        HBox groupsHeader = new HBox(8);
        groupsHeader.setAlignment(Pos.CENTER_LEFT);
        groupsHeader.setPadding(new Insets(14));
        groupsHeader.setStyle("-fx-border-color: transparent transparent #e4e4e7 transparent; -fx-border-width:0 0 1 0;");
        Label groupsTitle = new Label("Your Groups");
        groupsTitle.setStyle("-fx-font-size: 16px; -fx-font-weight: 800; -fx-text-fill: #18181b;");
        Region groupsHeaderSpacer = new Region();
        HBox.setHgrow(groupsHeaderSpacer, Priority.ALWAYS);
        Button addGroupBtn = new Button("+");
        addGroupBtn.setStyle("-fx-background-color:#dcfce7; -fx-text-fill:#15803d; -fx-font-weight:800; -fx-font-size:15px; "
                + "-fx-background-radius:8; -fx-min-width:30; -fx-min-height:28; -fx-max-width:30; -fx-max-height:28; -fx-cursor:hand;");
        groupsHeader.getChildren().addAll(groupsTitle, groupsHeaderSpacer, addGroupBtn);

        TextField searchField = new TextField();
        searchField.setPromptText("Search topics...");
        searchField.setStyle("-fx-background-color:#fafafa; -fx-border-color:#e4e4e7; -fx-border-radius:10; "
                + "-fx-background-radius:10; -fx-padding:8 12 8 12; -fx-font-size:12.5px;");

        VBox groupList = new VBox(4);
        Label loadingGroups = new Label("Loading your groups\u2026");
        loadingGroups.setStyle("-fx-text-fill: #a1a1aa; -fx-font-style: italic; -fx-font-size: 12px; -fx-padding: 8;");
        groupList.getChildren().add(loadingGroups);

        VBox groupsInner = new VBox(10, searchField, groupList);
        groupsInner.setPadding(new Insets(10));
        groupsCol.getChildren().addAll(groupsHeader, groupsInner);

        // ---- chat column ----
        VBox chatCol = new VBox();
        HBox.setHgrow(chatCol, Priority.ALWAYS);

        HBox chatHeader = new HBox();
        chatHeader.setAlignment(Pos.CENTER_LEFT);
        chatHeader.setPadding(new Insets(14, 20, 14, 20));
        chatHeader.setStyle("-fx-background-color:white; -fx-border-color: transparent transparent #e4e4e7 transparent; -fx-border-width:0 0 1 0;");
        VBox chatHeaderText = new VBox(2);
        Label chatTitle = new Label(presetGroupName != null ? presetGroupName : "Discussions");
        chatTitle.setStyle("-fx-font-size: 16px; -fx-font-weight: 800; -fx-text-fill: #18181b;");
        Label chatSub = new Label("Select a topic to view messages");
        chatSub.getStyleClass().add("muted");
        chatHeaderText.getChildren().addAll(chatTitle, chatSub);
        chatHeader.getChildren().add(chatHeaderText);

        Label offlineBanner = new Label("You're offline — showing the last saved messages for this topic.");
        offlineBanner.setStyle("-fx-background-color:#fef3c7; -fx-text-fill:#92400e; -fx-font-size:11.5px; -fx-padding:6 14 6 14; -fx-font-weight:600;");
        offlineBanner.setMaxWidth(Double.MAX_VALUE);
        offlineBanner.setVisible(false);
        offlineBanner.setManaged(false);

        VBox messages = new VBox(20);
        messages.setPadding(new Insets(20));
        ScrollPane chatScroll = new ScrollPane(messages);
        chatScroll.setFitToWidth(true);
        chatScroll.getStyleClass().add("scroll-pane");
        VBox.setVgrow(chatScroll, Priority.ALWAYS);

        HBox composer = new HBox(10);
        composer.setPadding(new Insets(14));
        composer.setAlignment(Pos.CENTER_LEFT);
        composer.setStyle("-fx-background-color: white; -fx-border-color: #e4e4e7 transparent transparent transparent; -fx-border-width: 1 0 0 0;");
        TextField messageInput = new TextField();
        messageInput.setPromptText("Type a message...");
        messageInput.setDisable(true);
        HBox.setHgrow(messageInput, Priority.ALWAYS);
        Button sendBtn = new Button("Send");
        sendBtn.getStyleClass().add("btn-primary");
        sendBtn.setDisable(true);
        composer.getChildren().addAll(messageInput, sendBtn);

        chatCol.getChildren().addAll(chatHeader, offlineBanner, chatScroll, composer);

        // ---- welcome pane (shown until a topic is opened) ----
        Runnable[] refreshFromServer = new Runnable[1]; // wired further down; the welcome cards need it now
        VBox welcomePane = buildWelcomePane(() -> refreshFromServer[0].run());

        StackPane rightArea = new StackPane(welcomePane, chatCol);
        HBox.setHgrow(rightArea, Priority.ALWAYS);

        boolean startOnWelcome = presetGroupId <= 0;
        chatCol.setVisible(!startOnWelcome);
        chatCol.setManaged(!startOnWelcome);
        welcomePane.setVisible(startOnWelcome);
        welcomePane.setManaged(startOnWelcome);

        Runnable showChatPane = () -> {
            welcomePane.setVisible(false);
            welcomePane.setManaged(false);
            chatCol.setVisible(true);
            chatCol.setManaged(true);
        };

        HBox body = new HBox(groupsCol, rightArea);
        body.getStyleClass().add("bg-cream");
        root.setCenter(body);

        // ---- state: which topic is open, expand/collapse + search state, cached group data ----
        long[] currentTopicId = {-1};
        // One-shot: cleared after the first render so later poll-driven
        // refreshes don't keep re-highlighting an old reply forever.
        long[] highlightPostId = {presetPostId};
        Set<Long> expandedGroups = new HashSet<>();
        @SuppressWarnings("unchecked")
        List<GroupService.Group>[] lastGroups = new List[]{ List.of() };
        String[] searchQuery = { "" };
        boolean[] firstLoad = { true };

        Runnable[] loadMessages = new Runnable[1];
        loadMessages[0] = () -> {
            if (currentTopicId[0] <= 0) return;
            Async.run(
                    () -> PostService.list(currentTopicId[0]),
                    result -> {
                        renderMessages(messages, result.posts, highlightPostId[0]);
                        scrollToHighlighted(chatScroll, messages, result.posts, highlightPostId[0]);
                        highlightPostId[0] = -1;
                        offlineBanner.setVisible(result.fromCache);
                        offlineBanner.setManaged(result.fromCache);
                    },
                    err -> renderMessagesError(messages, friendlyMessage(err))
            );
        };

        sendBtn.setOnAction(e -> {
            String text = messageInput.getText().trim();
            if (text.isEmpty() || currentTopicId[0] <= 0) return;
            sendBtn.setDisable(true);
            Async.run(
                    () -> { PostService.send(currentTopicId[0], text); return null; },
                    v -> {
                        sendBtn.setDisable(false);
                        messageInput.clear();
                        loadMessages[0].run();
                    },
                    err -> {
                        sendBtn.setDisable(false);
                        new Alert(Alert.AlertType.ERROR, friendlyMessage(err)).showAndWait();
                    }
            );
        });
        messageInput.setOnAction(e -> sendBtn.fire());

        // Poll for new messages so a post made on the web shows up here too.
        LivePoller.start(root, Duration.seconds(4), loadMessages[0]);

        LongConsumer openTopic = topicId -> {
            currentTopicId[0] = topicId;
            showChatPane.run();
            renderLoadingMessages(messages);
            loadMessages[0].run();
        };

        // Cheap re-render (uses the already-fetched list — no network call): used for
        // expand/collapse toggles and live search-as-you-type.
        Runnable[] rerender = new Runnable[1];
        rerender[0] = () -> renderGroupList(groupList, lastGroups[0], searchQuery[0], expandedGroups,
                chatTitle, chatSub, messageInput, sendBtn, openTopic,
                () -> rerender[0].run(), () -> refreshFromServer[0].run());

        // Full refetch: used on first load, after create/join/add-topic actions, and every
        // few seconds so changes made on the web (or by another device) show up here too.
        refreshFromServer[0] = () -> Async.run(
                GroupService::list,
                result -> {
                    lastGroups[0] = result.myGroups;

                    if (result.myGroups.isEmpty()) {
                        groupList.getChildren().clear();
                        Label empty = new Label("You haven't joined a group yet.");
                        empty.setStyle("-fx-text-fill: #a1a1aa; -fx-font-style: italic; -fx-font-size: 12px; -fx-padding: 8;");
                        Button toDashboard = new Button("Go to Dashboard to join one");
                        toDashboard.getStyleClass().add("btn-outline");
                        toDashboard.setOnAction(ev -> Router.get().navigate(StudentDashboardScreen::build));
                        groupList.getChildren().addAll(empty, toDashboard);
                        if (firstLoad[0]) {
                            firstLoad[0] = false;
                            chatSub.setText("No groups joined yet");
                        }
                        return;
                    }

                    if (firstLoad[0]) {
                        firstLoad[0] = false;
                        if (presetGroupId > 0) {
                            GroupService.Group targetGroup = result.myGroups.stream()
                                    .filter(g -> g.id == presetGroupId)
                                    .findFirst()
                                    .orElse(result.myGroups.get(0));
                            expandedGroups.add(targetGroup.id);

                            long topicToOpen = presetTopicId > 0 ? presetTopicId
                                    : targetGroup.topics.stream()
                                    .filter(t -> t.canAccess)
                                    .findFirst()
                                    .map(t -> t.id)
                                    .orElse(-1L);
                            chatTitle.setText(targetGroup.name);
                            if (topicToOpen > 0) {
                                currentTopicId[0] = topicToOpen;
                                String topicTitle = targetGroup.topics.stream()
                                        .filter(t -> t.id == topicToOpen).findFirst()
                                        .map(t -> t.title).orElse("Topic");
                                chatSub.setText(topicTitle);
                                messageInput.setDisable(false);
                                sendBtn.setDisable(false);
                                renderLoadingMessages(messages);
                                loadMessages[0].run();
                            } else if (targetGroup.topics.isEmpty()) {
                                chatSub.setText("This group has no topics yet");
                            } else {
                                chatSub.setText("Select a topic to view messages");
                            }
                        }
                    }

                    rerender[0].run();
                },
                err -> {
                    groupList.getChildren().clear();
                    Label errLabel = new Label("Couldn't load your groups: " + friendlyMessage(err));
                    errLabel.setWrapText(true);
                    errLabel.setStyle("-fx-text-fill: #dc2626; -fx-font-size: 12px; -fx-padding: 8;");
                    groupList.getChildren().add(errLabel);
                }
        );

        searchField.textProperty().addListener((obs, old, val) -> {
            searchQuery[0] = val;
            rerender[0].run();
        });

        addGroupBtn.setOnAction(e -> CreateGroupDialog.show(() -> refreshFromServer[0].run()));

        refreshFromServer[0].run();
        // No websocket layer on desktop yet — poll so a group/topic created elsewhere (web,
        // another device) shows up here without the user having to reload the screen.
        LivePoller.start(root, Duration.seconds(8), refreshFromServer[0]);

        return root;
    }

    // ---- Welcome state (shown until a topic is opened) --------------------

    private static VBox buildWelcomePane(Runnable onChanged) {
        VBox pane = new VBox(20);
        pane.setAlignment(Pos.CENTER);
        pane.setPadding(new Insets(60));

        StackPane iconBox = new StackPane();
        iconBox.setStyle("-fx-background-color:#f0fdf4; -fx-background-radius:20; -fx-min-width:68; -fx-min-height:68; -fx-max-width:68; -fx-max-height:68;");
        Label icon = new Label("\uD83D\uDCAC");
        icon.setStyle("-fx-font-size:28px;");
        iconBox.getChildren().add(icon);

        Label title = new Label("Welcome to Discussions");
        title.setStyle("-fx-font-size:24px; -fx-font-weight:800; -fx-text-fill:#18181b;");

        Label sub = new Label("Select a topic from the sidebar to start chatting, or explore new groups to expand your network.");
        sub.setWrapText(true);
        sub.setMaxWidth(440);
        sub.setStyle("-fx-font-size:13px; -fx-text-fill:#71717a; -fx-text-alignment:center;");
        sub.setAlignment(Pos.CENTER);

        HBox cards = new HBox(20);
        cards.setAlignment(Pos.CENTER);
        cards.getChildren().addAll(
                welcomeActionCard("+", "#dcfce7", "#15803d", "Create a Group",
                        "Start a new study circle, define your topics, and manage members.",
                        () -> CreateGroupDialog.show(onChanged)),
                welcomeActionCard("\uD83D\uDD0D", "#dbeafe", "#2563eb", "Join a Group",
                        "Browse existing active groups and join the ongoing academic discussions.",
                        () -> JoinGroupDialog.show(onChanged))
        );

        pane.getChildren().addAll(iconBox, title, sub, cards);
        return pane;
    }

    private static VBox welcomeActionCard(String glyph, String iconBg, String iconFg, String title, String desc, Runnable action) {
        VBox card = new VBox(12);
        card.setAlignment(Pos.CENTER);
        card.setPrefWidth(230);
        card.setMaxWidth(230);
        card.setPadding(new Insets(24, 18, 24, 18));
        card.setStyle("-fx-background-color:white; -fx-border-color:#e4e4e7; -fx-border-radius:14; -fx-background-radius:14; -fx-cursor:hand;");

        StackPane iconBox = new StackPane();
        iconBox.setStyle("-fx-background-color:" + iconBg + "; -fx-background-radius:100; -fx-min-width:46; -fx-min-height:46; -fx-max-width:46; -fx-max-height:46;");
        Label icon = new Label(glyph);
        icon.setStyle("-fx-font-size:16px; -fx-text-fill:" + iconFg + "; -fx-font-weight:800;");
        iconBox.getChildren().add(icon);

        Label t = new Label(title);
        t.setStyle("-fx-font-weight:800; -fx-font-size:14px; -fx-text-fill:#18181b;");

        Label d = new Label(desc);
        d.setWrapText(true);
        d.setAlignment(Pos.CENTER);
        d.setStyle("-fx-font-size:11.5px; -fx-text-fill:#71717a; -fx-text-alignment:center;");

        card.getChildren().addAll(iconBox, t, d);
        card.setOnMouseClicked(e -> action.run());
        return card;
    }

    // ---- Groups/topics sidebar tree (collapsible, with search filtering) --

    private static void renderGroupList(VBox groupList, List<GroupService.Group> groups, String query,
                                        Set<Long> expandedGroups, Label chatTitle, Label chatSub,
                                        TextField messageInput, Button sendBtn, LongConsumer onOpenTopic,
                                        Runnable rerender, Runnable refreshFromServer) {
        groupList.getChildren().clear();
        if (groups.isEmpty()) {
            Label empty = new Label("You haven't joined a group yet.");
            empty.setStyle("-fx-text-fill: #a1a1aa; -fx-font-style: italic; -fx-font-size: 12px; -fx-padding: 8;");
            groupList.getChildren().add(empty);
            return;
        }

        String q = query == null ? "" : query.trim().toLowerCase();
        boolean searching = !q.isEmpty();
        boolean anyMatch = false;

        for (GroupService.Group g : groups) {
            boolean nameMatches = searching && g.name.toLowerCase().contains(q);
            List<GroupService.Topic> visibleTopics;
            if (!searching || nameMatches) {
                visibleTopics = g.topics;
            } else {
                visibleTopics = g.topics.stream().filter(t -> t.title.toLowerCase().contains(q)).toList();
            }
            if (searching && !nameMatches && visibleTopics.isEmpty()) continue;
            anyMatch = true;

            boolean expanded = searching || expandedGroups.contains(g.id);
            groupList.getChildren().add(groupSection(g, visibleTopics, expanded, searching, expandedGroups,
                    chatTitle, chatSub, messageInput, sendBtn, onOpenTopic, rerender, refreshFromServer));
        }

        if (searching && !anyMatch) {
            Label none = new Label("No topics found for \"" + query.trim() + "\".");
            none.setWrapText(true);
            none.setStyle("-fx-text-fill:#a1a1aa; -fx-font-style: italic; -fx-font-size: 12px; -fx-padding: 8;");
            groupList.getChildren().add(none);
        }
    }

    private static VBox groupSection(GroupService.Group g, List<GroupService.Topic> visibleTopics, boolean expanded,
                                     boolean searching, Set<Long> expandedGroups, Label chatTitle, Label chatSub,
                                     TextField messageInput, Button sendBtn, LongConsumer onOpenTopic,
                                     Runnable rerender, Runnable refreshFromServer) {
        VBox section = new VBox(2);

        HBox header = new HBox(8);
        header.setAlignment(Pos.CENTER_LEFT);
        header.setPadding(new Insets(8));
        header.setStyle("-fx-cursor:hand; -fx-background-radius:8;");
        Label chevron = new Label(expanded ? "\u25BE" : "\u25B8");
        chevron.setStyle("-fx-text-fill:#a1a1aa; -fx-font-size:11px;");
        Label groupLabel = new Label(g.name);
        groupLabel.setStyle("-fx-font-weight: 700; -fx-text-fill: #3f3f46; -fx-font-size:13px;");
        Region hspacer = new Region();
        HBox.setHgrow(hspacer, Priority.ALWAYS);
        header.getChildren().addAll(chevron, groupLabel, hspacer);
        if (!searching) {
            header.setOnMouseClicked(e -> {
                if (expandedGroups.contains(g.id)) expandedGroups.remove(g.id); else expandedGroups.add(g.id);
                rerender.run();
            });
        }
        section.getChildren().add(header);

        if (expanded) {
            VBox topicsBox = new VBox(3);
            topicsBox.setPadding(new Insets(0, 0, 4, 20));
            topicsBox.setStyle("-fx-border-color: transparent transparent transparent #e4e4e7; -fx-border-width: 0 0 0 2;");
            if (visibleTopics.isEmpty()) {
                Label none = new Label(searching ? "No matching topics" : "No topics yet");
                none.setStyle("-fx-text-fill: #a1a1aa; -fx-font-size: 11.5px; -fx-padding: 6 6 6 10;");
                topicsBox.getChildren().add(none);
            } else {
                for (GroupService.Topic t : visibleTopics) {
                    topicsBox.getChildren().add(topicRow(t, g, chatTitle, chatSub, messageInput, sendBtn, onOpenTopic));
                }
            }
            if (!searching) {
                HBox addTopicRow = new HBox(6);
                addTopicRow.setAlignment(Pos.CENTER_LEFT);
                addTopicRow.setPadding(new Insets(6, 6, 6, 10));
                addTopicRow.setStyle("-fx-cursor:hand;");
                Label plus = new Label("+");
                plus.setStyle("-fx-text-fill:#2F7A54; -fx-font-weight:800; -fx-font-size:12px;");
                Label addLabel = new Label("Add Topic");
                addLabel.setStyle("-fx-text-fill:#2F7A54; -fx-font-size:12px; -fx-font-weight:600;");
                addTopicRow.getChildren().addAll(plus, addLabel);
                addTopicRow.setOnMouseClicked(e -> CreateTopicDialog.show(g.id, () -> {
                    expandedGroups.add(g.id);
                    refreshFromServer.run();
                }));
                topicsBox.getChildren().add(addTopicRow);
            }
            section.getChildren().add(topicsBox);
        }

        return section;
    }

    private static Node topicRow(GroupService.Topic t, GroupService.Group g, Label chatTitle, Label chatSub,
                                 TextField messageInput, Button sendBtn, LongConsumer onOpenTopic) {
        String prefix = t.isPrivate ? "\uD83D\uDD12  " : "#  ";
        Label topicLabel = new Label(prefix + t.title);
        topicLabel.setMaxWidth(Double.MAX_VALUE);

        if (t.canAccess) {
            topicLabel.setStyle("-fx-text-fill:#3f3f46; -fx-padding:8; -fx-font-size:12.5px; -fx-cursor: hand;");
            topicLabel.setOnMouseClicked(e -> {
                chatTitle.setText(g.name);
                chatSub.setText(t.title);
                messageInput.setDisable(false);
                sendBtn.setDisable(false);
                onOpenTopic.accept(t.id);
            });
            return topicLabel;
        } else {
            boolean pending = "pending".equals(t.participantStatus);
            topicLabel.setStyle("-fx-text-fill:#a1a1aa; -fx-padding:8 4 8 8; -fx-font-size:12.5px;");

            HBox row = new HBox(topicLabel);
            row.setStyle("-fx-alignment: center-left;");
            HBox.setHgrow(topicLabel, Priority.ALWAYS);

            Button requestBtn = new Button(pending ? "Pending" : "Request Access");
            requestBtn.getStyleClass().add("btn-outline");
            requestBtn.setStyle("-fx-font-size: 10.5px; -fx-padding: 3 8 3 8;");
            requestBtn.setDisable(pending);
            requestBtn.setOnAction(e -> {
                requestBtn.setDisable(true);
                Async.run(
                        () -> { TopicService.requestAccess(t.id); return null; },
                        v -> requestBtn.setText("Pending"),
                        err -> {
                            requestBtn.setDisable(false);
                            new Alert(Alert.AlertType.ERROR,
                                    "Couldn't request access: " + friendlyMessage(err)).showAndWait();
                        }
                );
            });
            row.getChildren().add(requestBtn);
            return row;
        }
    }

    // ---- chat rendering (unchanged from before) ----------------------------

    private static void renderLoadingMessages(VBox messages) {
        messages.getChildren().setAll(italicNote("Loading messages\u2026"));
    }

    private static void renderMessagesError(VBox messages, String msg) {
        Label l = italicNote("Couldn't load messages: " + msg);
        l.setStyle(l.getStyle() + "-fx-text-fill:#dc2626;");
        messages.getChildren().setAll(l);
    }

    private static Label italicNote(String text) {
        Label l = new Label(text);
        l.setStyle("-fx-text-fill:#a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");
        return l;
    }

    private static void renderMessages(VBox messages, List<PostService.Post> posts, long highlightPostId) {
        // Skip the "don't reset scroll on every poll" shortcut whenever
        // there's a highlight pending — that check exists to avoid
        // rebuilding on unrelated poll ticks, but a fresh highlight always
        // needs a real (re)build so the styling actually applies.
        if (highlightPostId <= 0 && messages.getChildren().size() == posts.size() && !posts.isEmpty()) {
            return;
        }
        messages.getChildren().clear();
        if (posts.isEmpty()) {
            messages.getChildren().add(italicNote("No messages yet — say hello!"));
            return;
        }
        String me = com.sdf.session.Session.get().getName();
        for (PostService.Post p : posts) {
            boolean mine = me != null && me.equalsIgnoreCase(p.authorName);
            HBox row = mine ? outgoingMessage(p) : incomingMessage(p);
            if (p.id == highlightPostId) {
                row.setStyle(row.getStyle() + "-fx-background-color: #fef9c3; -fx-background-radius: 8; -fx-padding: 6;");
            }
            messages.getChildren().add(row);
        }
    }

    /** Best-effort: scroll so the highlighted reply is roughly in view. */
    private static void scrollToHighlighted(ScrollPane chatScroll, VBox messages, List<PostService.Post> posts, long highlightPostId) {
        if (highlightPostId <= 0) return;
        int index = -1;
        for (int i = 0; i < posts.size(); i++) {
            if (posts.get(i).id == highlightPostId) { index = i; break; }
        }
        if (index < 0) return; // outside the last-50 window loaded — nothing more we can do
        double fraction = posts.size() <= 1 ? 0 : (double) index / (posts.size() - 1);
        Platform.runLater(() -> chatScroll.setVvalue(fraction));
    }

    private static HBox incomingMessage(PostService.Post p) {
        VBox bubble = new VBox(4);
        Label a = new Label(p.authorName);
        a.setStyle("-fx-font-size: 11px; -fx-text-fill: #71717a;");
        Label t = new Label(p.content);
        t.setWrapText(true);
        t.setMaxWidth(420);
        VBox inner = new VBox(t);
        inner.getStyleClass().add("chat-bubble-in");
        Label time1 = new Label(shortTime(p.timestamp));
        time1.setStyle("-fx-font-size: 10px; -fx-text-fill: #a1a1aa;");
        bubble.getChildren().addAll(a, inner, time1);
        HBox row = new HBox(bubble);
        row.setAlignment(Pos.CENTER_LEFT);
        return row;
    }

    private static HBox outgoingMessage(PostService.Post p) {
        VBox bubble = new VBox(4);
        bubble.setAlignment(Pos.CENTER_RIGHT);
        Label a = new Label("You");
        a.setStyle("-fx-font-size: 11px; -fx-text-fill: #71717a;");
        Label t = new Label(p.content);
        t.setWrapText(true);
        t.setMaxWidth(420);
        VBox inner = new VBox(t);
        inner.getStyleClass().add("chat-bubble-out");
        Label time1 = new Label(shortTime(p.timestamp));
        time1.setStyle("-fx-font-size: 10px; -fx-text-fill: #a1a1aa;");
        bubble.getChildren().addAll(a, inner, time1);
        HBox row = new HBox(bubble);
        row.setAlignment(Pos.CENTER_RIGHT);
        return row;
    }

    private static String shortTime(String timestamp) {
        if (timestamp != null && timestamp.length() >= 16 && timestamp.contains(" ")) {
            return timestamp.substring(11, 16);
        }
        return timestamp == null ? "" : timestamp;
    }

    private static String friendlyMessage(Exception err) {
        if (err instanceof ApiException ae) return ae.firstMessage();
        return "Couldn't reach the server. Check your connection and try again.";
    }
}