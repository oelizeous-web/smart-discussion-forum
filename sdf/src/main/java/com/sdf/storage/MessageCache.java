package com.sdf.storage;

import com.sdf.database.DatabaseConnection;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.List;

public final class MessageCache {

    public static final class CachedPost {
        public final long id;
        public final String authorName;
        public final String content;
        public final String timestamp;

        public CachedPost(long id, String authorName, String content, String timestamp) {
            this.id = id;
            this.authorName = authorName;
            this.content = content;
            this.timestamp = timestamp;
        }
    }

    private MessageCache() {
    }

    /** Replaces the cached snapshot for a topic. Called after a successful fetch. */
    public static void replace(long topicId, List<CachedPost> posts) {
        String deleteSql = "DELETE FROM cached_posts WHERE topic_id = ?";
        String insertSql = """
                INSERT INTO cached_posts (id, topic_id, author_name, content, timestamp)
                VALUES (?, ?, ?, ?, ?)
                """;

        try (Connection connection = DatabaseConnection.getConnection()) {
            connection.setAutoCommit(false);

            try (PreparedStatement deleteStatement = connection.prepareStatement(deleteSql)) {
                deleteStatement.setLong(1, topicId);
                deleteStatement.executeUpdate();
            }

            try (PreparedStatement insertStatement = connection.prepareStatement(insertSql)) {
                for (CachedPost post : posts) {
                    insertStatement.setLong(1, post.id);
                    insertStatement.setLong(2, topicId);
                    insertStatement.setString(3, post.authorName);
                    insertStatement.setString(4, post.content);
                    insertStatement.setString(5, post.timestamp);
                    insertStatement.addBatch();
                }
                insertStatement.executeBatch();
            }

            connection.commit();

        } catch (SQLException exception) {
            System.err.println("Could not cache messages for topic " + topicId + ": " + exception.getMessage());
        }
    }

    /** Never returns null — an empty list means nothing was ever cached for this topic. */
    public static List<CachedPost> read(long topicId) {
        String sql = "SELECT id, author_name, content, timestamp FROM cached_posts WHERE topic_id = ? ORDER BY id ASC";
        List<CachedPost> posts = new ArrayList<>();

        try (Connection connection = DatabaseConnection.getConnection();
             PreparedStatement statement = connection.prepareStatement(sql)) {

            statement.setLong(1, topicId);

            try (ResultSet resultSet = statement.executeQuery()) {
                while (resultSet.next()) {
                    posts.add(new CachedPost(
                            resultSet.getLong("id"),
                            resultSet.getString("author_name"),
                            resultSet.getString("content"),
                            resultSet.getString("timestamp")
                    ));
                }
            }

        } catch (SQLException exception) {
            System.err.println("Could not read cached messages for topic " + topicId + ": " + exception.getMessage());
        }

        return posts;
    }
}
