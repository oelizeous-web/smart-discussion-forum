package com.sdf.api;

import com.fasterxml.jackson.databind.JsonNode;

import java.util.ArrayList;
import java.util.Iterator;
import java.util.LinkedHashMap;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Map;
import java.util.Set;

/**
 * A handful of the endpoints in the API spec (admin/groups, the quiz
 * report) don't document their exact response shape. Rather than
 * guessing field names and silently showing blank columns when we
 * guess wrong, we parse whatever JSON comes back into generic ordered
 * rows and let the screen build table columns from whatever keys
 * actually show up. If the real backend response uses different
 * field names than we expect elsewhere, this still displays something
 * useful instead of an empty table.
 */
public final class JsonTable {

    private JsonTable() {}

    /** One row = one flattened JSON object, keys in first-seen order. */
    public static final class Row {
        public final Map<String, String> cells = new LinkedHashMap<>();
    }

    public static final class Result {
        public final List<String> columns = new ArrayList<>();
        public final List<Row> rows = new ArrayList<>();
    }

    public static Result parse(JsonNode json) {
        Result result = new Result();
        JsonNode arr = findArray(json);
        if (arr == null || !arr.isArray()) return result;

        Set<String> columnSet = new LinkedHashSet<>();
        for (JsonNode item : arr) {
            Row row = new Row();
            flatten("", item, row.cells);
            columnSet.addAll(row.cells.keySet());
            result.rows.add(row);
        }
        result.columns.addAll(columnSet);
        return result;
    }

    private static JsonNode findArray(JsonNode json) {
        if (json == null) return null;
        if (json.isArray()) return json;
        for (String key : new String[]{"data", "groups", "results", "report", "students", "rows", "items"}) {
            if (json.has(key) && json.get(key).isArray()) return json.get(key);
        }
        return null;
    }

    private static void flatten(String prefix, JsonNode node, Map<String, String> out) {
        if (node == null || node.isNull()) return;
        if (node.isObject()) {
            Iterator<Map.Entry<String, JsonNode>> fields = node.fields();
            while (fields.hasNext()) {
                Map.Entry<String, JsonNode> e = fields.next();
                String key = prefix.isEmpty() ? e.getKey() : prefix + "." + e.getKey();
                JsonNode value = e.getValue();
                if (value.isObject()) {
                    flatten(key, value, out);
                } else if (value.isArray()) {
                    out.put(key, value.toString());
                } else {
                    out.put(key, value.asText(""));
                }
            }
        } else {
            out.put(prefix.isEmpty() ? "value" : prefix, node.asText(""));
        }
    }
}
