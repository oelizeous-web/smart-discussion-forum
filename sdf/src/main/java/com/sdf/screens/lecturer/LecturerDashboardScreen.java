package com.sdf.screens.lecturer;

import com.sdf.Router;
import com.sdf.api.ApiException;
import com.sdf.api.Async;
import com.sdf.api.GroupService;
import com.sdf.components.Sidebar;
import com.sdf.components.UiKit;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.control.Label;
import javafx.scene.control.ScrollPane;
import javafx.scene.layout.BorderPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;

import java.util.LinkedHashMap;

/**
 * Your api.php has no dashboard/analytics endpoint, so this screen
 * shows what IS real: your groups and their topics, from GET
 * /api/groups (CONFIRMED), plus quick links to the other screens.
 */
public class LecturerDashboardScreen {

    public static Parent build() {
        BorderPane root = new BorderPane();
        root.getStyleClass().add("app-shell");

        LinkedHashMap<String, Runnable> links = new LinkedHashMap<>();
        links.put("Dashboard", null);
        links.put("Students", () -> Router.get().navigate(LecturerStudentsScreen::build));
        links.put("Grades", () -> Router.get().navigate(LecturerGradesScreen::build));
        links.put("Quiz Control", () -> Router.get().navigate(LecturerQuizBuilderScreen::build));
        links.put("Reports", () -> Router.get().navigate(LecturerReportsScreen::build));
        root.setLeft(new Sidebar("Dashboard", com.sdf.session.Session.get().getInitial(), com.sdf.session.Session.get().getName(), links));

        VBox content = new VBox(20);
        content.getStyleClass().add("bg-gray-page");
        content.setPadding(new Insets(28));

        // ---------- Masthead: eyebrow + title + subtitle + live KPIs ----------
        Label eyebrow = new Label("LECTURER \u00B7 LIVE SESSION");
        eyebrow.getStyleClass().add("pill-eyebrow");

        Label title = UiKit.pageTitle("Welcome back, " + com.sdf.session.Session.get().getName());
        title.setStyle("-fx-font-size: 22px; -fx-font-weight: 800; -fx-text-fill: #18181b;");

        Label sub = UiKit.subtitle("Your groups and topics.");

        VBox titleBlock = new VBox(8, eyebrow, title, sub);

        StatBox groupsStat = statBox("Groups", "#2F7A54");
        HBox statsRow = new HBox(12, groupsStat.node);
        statsRow.setAlignment(Pos.CENTER_LEFT);

        HBox header = UiKit.spacerRow(titleBlock, statsRow);
        header.setAlignment(Pos.CENTER_LEFT);
        header.getStyleClass().add("card");

        // signature accent rail along the top of the masthead card
        Region accentRail = new Region();
        accentRail.setStyle("-fx-background-color: linear-gradient(to right, #2F7A54, #5CC98B, #2F7A54); -fx-background-radius: 14 14 0 0;");
        accentRail.setPrefHeight(4);
        accentRail.setMaxHeight(4);
        VBox headerBlock = new VBox(0, accentRail, header);

        // ---------- Quick links ----------
        VBox groupsList = new VBox(12);
        Label loading = new Label("Loading your groups\u2026");
        loading.setStyle("-fx-text-fill:#a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");
        groupsList.getChildren().add(loading);

        HBox quickLinks = new HBox(14,
                quickLinkCard("Students Directory", "Manage rosters and standings", "#2F7A54", () -> Router.get().navigate(LecturerStudentsScreen::build)),
                quickLinkCard("Grades Ledger", "Review quiz reports", "#d97706", () -> Router.get().navigate(LecturerGradesScreen::build)),
                quickLinkCard("Quiz Control", "Build and publish assessments", "#2563eb", () -> Router.get().navigate(LecturerQuizBuilderScreen::build)),
                quickLinkCard("Reports", "Quiz performance reports", "#7c3aed", () -> Router.get().navigate(LecturerReportsScreen::build))
        );
        for (var n : quickLinks.getChildren()) HBox.setHgrow(n, Priority.ALWAYS);

        HBox groupsSectionHeader = new HBox(10, UiKit.sectionTitle("YOUR GROUPS"));
        groupsSectionHeader.setAlignment(Pos.CENTER_LEFT);
        Label groupsCountBadge = UiKit.badge("\u2014", "badge-outline-green");
        groupsSectionHeader.getChildren().add(groupsCountBadge);

        content.getChildren().addAll(headerBlock, quickLinks, groupsSectionHeader, groupsList);
        ScrollPane scroll = new ScrollPane(content);
        scroll.setFitToWidth(true);
        scroll.getStyleClass().add("scroll-pane");
        root.setCenter(scroll);

        // NOTE: /groups/manageable returns every group on the platform (what "all
        // groups in the forum" needs) but only ever includes a topic_count number,
        // never the real topic titles -- the dedicated /groups/{id}/topics endpoint
        // that would give us titles is broken server-side (no controller method).
        // So group rows below show the real topic COUNT (via topic_count), not a
        // titled list.
        Async.run(
                GroupService::listManageable,
                groups -> {
                    groupsList.getChildren().clear();
                    if (groups.isEmpty()) {
                        Label empty = new Label("No discussion groups found.");
                        empty.setStyle("-fx-text-fill:#a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");
                        groupsList.getChildren().add(empty);
                    } else {
                        for (GroupService.Group g : groups) {
                            groupsList.getChildren().add(groupRow(g));
                        }
                    }
                    groupsStat.valueLabel.setText(String.valueOf(groups.size()));
                    groupsCountBadge.setText(groups.size() + (groups.size() == 1 ? " group" : " groups"));
                },
                err -> {
                    groupsList.getChildren().clear();
                    Label errLbl = new Label("Couldn't load groups: " + friendlyMessage(err));
                    errLbl.setStyle("-fx-text-fill:#dc2626; -fx-font-size: 12px;");
                    groupsList.getChildren().add(errLbl);
                }
        );

        return root;
    }

    /** Holds a stat card's root node alongside a reference to its mutable value label. */
    private static final class StatBox {
        final VBox node;
        final Label valueLabel;
        StatBox(VBox node, Label valueLabel) { this.node = node; this.valueLabel = valueLabel; }
    }

    private static StatBox statBox(String label, String valueColorHex) {
        VBox box = new VBox(10);
        box.getStyleClass().add("stat-card");
        Label l = new Label(label.toUpperCase());
        l.getStyleClass().add("stat-label");
        Label v = new Label("\u2014");
        v.getStyleClass().add("stat-value");
        v.setStyle("-fx-text-fill: " + valueColorHex + ";");
        box.getChildren().addAll(l, v);
        box.setMinWidth(96);
        HBox.setHgrow(box, Priority.ALWAYS);
        return new StatBox(box, v);
    }

    private static VBox groupRow(GroupService.Group g) {
        VBox box = UiKit.card();

        StackPane avatar = new StackPane();
        avatar.getStyleClass().add("avatar-circle");
        avatar.setStyle("-fx-background-color: #2F7A54; -fx-min-width: 34; -fx-min-height: 34; -fx-max-width: 34; -fx-max-height: 34;");
        Label avatarText = new Label(g.name != null && !g.name.isEmpty() ? g.name.substring(0, 1).toUpperCase() : "?");
        avatarText.getStyleClass().add("avatar-text");
        avatarText.setStyle("-fx-text-fill: white; -fx-font-size: 13px;");
        avatar.getChildren().add(avatarText);

        Label n = new Label(g.name);
        n.setStyle("-fx-font-weight: 800; -fx-font-size: 14px; -fx-text-fill: #18181b;");

        Label countText = new Label(g.topicCount + (g.topicCount == 1 ? " topic" : " topics"));
        countText.setStyle("-fx-text-fill:#71717a; -fx-font-size: 11px; -fx-font-weight: 600;");

        HBox rowHeader = new HBox(10, avatar, n, countText);
        rowHeader.setAlignment(Pos.CENTER_LEFT);
        box.getChildren().add(rowHeader);

        return box;
    }

    private static VBox quickLinkCard(String title, String desc, String accentHex, Runnable onClick) {
        VBox box = UiKit.card();
        box.setCursor(javafx.scene.Cursor.HAND);

        Region topAccent = new Region();
        topAccent.setStyle("-fx-background-color: " + accentHex + "; -fx-background-radius: 3;");
        topAccent.setPrefHeight(3);
        topAccent.setMaxHeight(3);
        topAccent.setMaxWidth(28);
        topAccent.setMinWidth(28);

        StackPane icon = new StackPane();
        icon.setStyle("-fx-background-color: derive(" + accentHex + ", 85%); -fx-background-radius: 10; -fx-min-width: 34; -fx-min-height: 34; -fx-max-width: 34; -fx-max-height: 34;");
        Label iconText = new Label(title.substring(0, 1).toUpperCase());
        iconText.setStyle("-fx-text-fill: " + accentHex + "; -fx-font-weight: 800; -fx-font-size: 14px;");
        icon.getChildren().add(iconText);

        Label t = new Label(title);
        t.setStyle("-fx-font-weight: 800; -fx-font-size: 13px; -fx-text-fill: #18181b;");
        Label d = new Label(desc);
        d.setWrapText(true);
        d.setStyle("-fx-font-size: 11px; -fx-text-fill: #71717a;");

        Label arrow = new Label("Open \u2192");
        arrow.setStyle("-fx-font-size: 10px; -fx-font-weight: 700; -fx-text-fill: " + accentHex + ";");

        Region spacer = new Region();
        VBox.setVgrow(spacer, Priority.ALWAYS);

        box.getChildren().addAll(topAccent, icon, t, d, spacer, arrow);
        box.setOnMouseClicked(e -> onClick.run());
        return box;
    }

    private static String friendlyMessage(Exception err) {
        if (err instanceof ApiException ae) return ae.firstMessage();
        return "Couldn't reach the server. Check your connection and try again.";
    }
}