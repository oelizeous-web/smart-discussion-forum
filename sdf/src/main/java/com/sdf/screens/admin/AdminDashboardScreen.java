package com.sdf.screens.admin;

import com.sdf.Router;
import com.sdf.api.AdminService;
import com.sdf.api.ApiException;
import com.sdf.api.Async;
import com.sdf.api.JsonTable;
import com.sdf.components.Sidebar;
import com.sdf.components.UiKit;
import com.fasterxml.jackson.databind.JsonNode;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.geometry.Insets;
import javafx.scene.Parent;
import javafx.scene.chart.CategoryAxis;
import javafx.scene.chart.LineChart;
import javafx.scene.chart.NumberAxis;
import javafx.scene.chart.XYChart;
import javafx.scene.control.Label;
import javafx.scene.control.TableColumn;
import javafx.scene.control.TableView;
import javafx.scene.layout.BorderPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.VBox;

import java.time.LocalDate;
import java.time.format.DateTimeFormatter;
import java.util.LinkedHashMap;

/**
 * Backed by GET /api/admin/dashboard (stat cards + 7-day activity) and
 * GET /api/admin/groups (the group table below it).
 */
public class AdminDashboardScreen {

    public static Parent build() {
        BorderPane root = new BorderPane();
        root.getStyleClass().add("app-shell");

        LinkedHashMap<String, Runnable> links = new LinkedHashMap<>();
        links.put("Dashboard", null);
        links.put("Members", () -> Router.get().navigate(AdminMembersScreen::build));
        links.put("Settings", () -> Router.get().navigate(AdminSettingsScreen::build));
        links.put("Reports", () -> Router.get().navigate(AdminReportsScreen::build));
        root.setLeft(new Sidebar("Dashboard", com.sdf.session.Session.get().getInitial(), com.sdf.session.Session.get().getName(), links));

        VBox content = new VBox(18);
        content.getStyleClass().add("bg-gray-page");
        content.setPadding(new Insets(28));

        Label title = new Label("Admin Dashboard");
        title.setStyle("-fx-font-size: 22px; -fx-font-weight: 800; -fx-text-fill: #18181b;");
        Label sub = new Label("Platform-wide activity, updated live from the server.");
        sub.getStyleClass().add("muted");

        HBox statsRow = new HBox(14);
        Label statsLoading = new Label("Loading stats\u2026");
        statsLoading.setStyle("-fx-text-fill:#a1a1aa; -fx-font-style: italic; -fx-font-size: 12px;");

        CategoryAxis xAxis = new CategoryAxis();
        NumberAxis yAxis = new NumberAxis();
        LineChart<String, Number> chart = new LineChart<>(xAxis, yAxis);
        chart.setTitle("7-Day Activity");
        chart.setPrefHeight(300);
        chart.setCreateSymbols(true);
        chart.setAnimated(false);

        Label groupsTitle = new Label("Groups Overview");
        groupsTitle.setStyle("-fx-font-size: 18px; -fx-font-weight: 800; -fx-text-fill: #18181b;");

        TableView<JsonTable.Row> table = new TableView<>();
        table.getStyleClass().add("data-table");
        table.setPrefHeight(360);
        table.setPlaceholder(new Label("Loading groups\u2026"));

        content.getChildren().addAll(title, sub, statsRow, statsLoading, chart, groupsTitle, table);
        root.setCenter(content);

        Async.run(
                AdminService::dashboardStats,
                json -> {
                    statsRow.getChildren().setAll(
                            UiKit.statCard("Groups", json.path("group_count").asText("0"), null),
                            UiKit.statCard("Members", json.path("member_count").asText("0"), null),
                            UiKit.statCard("Completion Rate", json.path("completion_rate").asText("0") + "%", null),
                            UiKit.statCard("Top Poster", json.path("top_poster").asText("\u2014"), null)
                    );
                    statsLoading.setVisible(false);
                    statsLoading.setManaged(false);

                    XYChart.Series<String, Number> postsSeries = new XYChart.Series<>();
                    postsSeries.setName("Posts Created");
                    XYChart.Series<String, Number> attemptsSeries = new XYChart.Series<>();
                    attemptsSeries.setName("Quiz Attempts");

                    DateTimeFormatter in = DateTimeFormatter.ISO_LOCAL_DATE;
                    DateTimeFormatter out = DateTimeFormatter.ofPattern("MMM d");
                    JsonNode activity = json.path("activity");
                    if (activity.isArray()) {
                        for (JsonNode day : activity) {
                            String label = day.path("date").asText("");
                            try {
                                label = LocalDate.parse(label, in).format(out);
                            } catch (Exception ignored) { /* fall back to raw string */ }
                            postsSeries.getData().add(new XYChart.Data<>(label, day.path("posts_created").asInt(0)));
                            attemptsSeries.getData().add(new XYChart.Data<>(label, day.path("quiz_attempts").asInt(0)));
                        }
                    }
                    chart.getData().addAll(postsSeries, attemptsSeries);
                },
                err -> {
                    statsLoading.setText("Couldn't load dashboard stats: " + friendlyMessage(err));
                    statsLoading.setStyle("-fx-text-fill:#dc2626; -fx-font-size: 12px;");
                }
        );

        Async.run(
                AdminService::listAllGroups,
                json -> {
                    JsonTable.Result parsed = JsonTable.parse(json);
                    if (parsed.rows.isEmpty()) {
                        table.setPlaceholder(new Label("No groups exist yet."));
                        return;
                    }
                    for (String col : parsed.columns) {
                        TableColumn<JsonTable.Row, String> c = new TableColumn<>(prettyColumnName(col));
                        c.setCellValueFactory(cell -> new SimpleStringProperty(cell.getValue().cells.getOrDefault(col, "")));
                        table.getColumns().add(c);
                    }
                    table.setItems(FXCollections.observableArrayList(parsed.rows));
                },
                err -> table.setPlaceholder(new Label("Couldn't load groups: " + friendlyMessage(err)))
        );

        VBox.setVgrow(table, Priority.ALWAYS);
        return root;
    }

    private static String prettyColumnName(String key) {
        String[] parts = key.replace(".", " ").split("_");
        StringBuilder sb = new StringBuilder();
        for (String p : parts) {
            if (p.isEmpty()) continue;
            if (sb.length() > 0) sb.append(" ");
            sb.append(Character.toUpperCase(p.charAt(0))).append(p.substring(1));
        }
        return sb.toString();
    }

    private static String friendlyMessage(Exception err) {
        if (err instanceof ApiException ae) return ae.firstMessage();
        return "Couldn't reach the server. Check your connection and try again.";
    }
}
