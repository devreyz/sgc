package br.rzin.sgc;

import java.net.URI;
import java.net.URISyntaxException;
import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

final class PathNavigationEngine {
    private final List<NavigationEntry> entries = new ArrayList<>();
    private boolean backNavigationPending;

    void recordNavigation(String url) {
        NavigationEntry entry = NavigationEntry.from(url);
        if (entry == null || backNavigationPending) {
            return;
        }

        int existingIndex = findLastIndex(entry.key());
        if (existingIndex >= 0) {
            truncateAfter(existingIndex);
            entries.set(existingIndex, entry);
            return;
        }

        entries.add(entry);
    }

    String beginBackNavigation() {
        if (backNavigationPending || entries.size() <= 1) {
            return null;
        }

        entries.remove(entries.size() - 1);
        backNavigationPending = true;

        return entries.get(entries.size() - 1).url();
    }

    void completePageLoad(String url) {
        NavigationEntry entry = NavigationEntry.from(url);
        if (entry == null) {
            return;
        }

        if (!backNavigationPending) {
            recordNavigation(url);
            return;
        }

        backNavigationPending = false;

        int existingIndex = findLastIndex(entry.key());
        if (existingIndex >= 0 && existingIndex < entries.size() - 1) {
            truncateAfter(existingIndex);
            entries.set(existingIndex, entry);
            return;
        }

        if (entries.isEmpty()) {
            entries.add(entry);
        } else {
            entries.set(entries.size() - 1, entry);
        }
    }

    boolean isBackNavigationPending() {
        return backNavigationPending;
    }

    int depth() {
        return entries.size();
    }

    private int findLastIndex(String key) {
        for (int index = entries.size() - 1; index >= 0; index--) {
            if (entries.get(index).key().equals(key)) {
                return index;
            }
        }

        return -1;
    }

    private void truncateAfter(int index) {
        while (entries.size() > index + 1) {
            entries.remove(entries.size() - 1);
        }
    }

    private record NavigationEntry(String url, String key) {
        private static NavigationEntry from(String url) {
            if (url == null || url.isBlank()) {
                return null;
            }

            try {
                URI uri = new URI(url).normalize();
                String scheme = lower(uri.getScheme());
                String host = lower(uri.getHost());

                if (!("http".equals(scheme) || "https".equals(scheme)) || host == null) {
                    return null;
                }

                int port = normalizedPort(scheme, uri.getPort());
                String path = uri.getRawPath();
                if (path == null || path.isEmpty()) {
                    path = "/";
                }

                StringBuilder key = new StringBuilder(scheme).append("://").append(host);
                if (port >= 0) {
                    key.append(':').append(port);
                }
                key.append(path);

                if (uri.getRawQuery() != null) {
                    key.append('?').append(uri.getRawQuery());
                }

                return new NavigationEntry(url, key.toString());
            } catch (URISyntaxException exception) {
                return null;
            }
        }

        private static String lower(String value) {
            return value == null ? null : value.toLowerCase(Locale.ROOT);
        }

        private static int normalizedPort(String scheme, int port) {
            if (("https".equals(scheme) && port == 443) || ("http".equals(scheme) && port == 80)) {
                return -1;
            }

            return port;
        }
    }
}
