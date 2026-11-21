package br.rzin.sgc;

import android.net.Uri;
import android.graphics.Bitmap;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceResponse;
import android.webkit.WebView;

import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeWebViewClient;

import java.io.IOException;
import java.util.Collections;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

final class NavigationWebViewClient extends BridgeWebViewClient {
    private static final String OFFLINE_PAGE = "file:///android_asset/offline.html";
    private static final String OFFLINE_ASSET_PATH = "/__sgc_android_offline/";
    private static final String PROVIDER_OFFLINE_PAGE = "https://sgc.rzin.com.br" + OFFLINE_ASSET_PATH + "provider-offline.html";
    private static final Pattern PROVIDER_ROUTE = Pattern.compile("^/([a-zA-Z0-9_-]+)/provider/(?:dashboard|orders(?:/(create|\\d+))?)/?$");
    private final PathNavigationEngine navigationEngine;
    private final MainActivity activity;

    NavigationWebViewClient(Bridge bridge, PathNavigationEngine navigationEngine, MainActivity activity) {
        super(bridge);
        this.navigationEngine = navigationEngine;
        this.activity = activity;
    }

    @Override
    public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
        if (handleAppUrl(view, request.getUrl())) {
            return true;
        }

        if (request.isForMainFrame() && isWebUrl(request.getUrl())) {
            activity.showNavigationLoading("Abrindo…");
        }

        return super.shouldOverrideUrlLoading(view, request);
    }

    @Override
    @SuppressWarnings("deprecation")
    public boolean shouldOverrideUrlLoading(WebView view, String url) {
        Uri uri = Uri.parse(url);
        if (handleAppUrl(view, uri)) {
            return true;
        }

        if (isWebUrl(uri)) {
            activity.showNavigationLoading("Abrindo…");
        }

        return super.shouldOverrideUrlLoading(view, url);
    }

    private boolean handleAppUrl(WebView view, Uri uri) {
        if (!"sgc".equals(uri.getScheme()) || !"onboarding-complete".equals(uri.getHost())) {
            return false;
        }

        activity.completeOnboarding(view);
        return true;
    }

    private boolean isWebUrl(Uri uri) {
        return uri != null
            && ("https".equalsIgnoreCase(uri.getScheme()) || "http".equalsIgnoreCase(uri.getScheme()));
    }

    @Override
    public WebResourceResponse shouldInterceptRequest(WebView view, WebResourceRequest request) {
        Uri uri = request.getUrl();
        String path = uri.getPath();
        if ("GET".equals(request.getMethod()) && isSgcOrigin(uri) && path != null && path.startsWith(OFFLINE_ASSET_PATH)) {
            String asset = path.substring(OFFLINE_ASSET_PATH.length());
            String contentType = switch (asset) {
                case "provider-offline.html" -> "text/html";
                case "assets/provider-offline.css" -> "text/css";
                case "assets/provider-offline.js", "assets/provider-offline-store.js",
                    "assets/provider-offline-document.js" -> "text/javascript";
                case "assets/icons/campo-dark-192.png" -> "image/png";
                default -> null;
            };
            if (contentType != null) {
                try {
                    return new WebResourceResponse(contentType, contentType.equals("image/png") ? null : "UTF-8",
                        200, "OK", Collections.singletonMap("Cache-Control", "no-store"),
                        activity.getAssets().open("provider-offline/" + asset));
                } catch (IOException error) {
                    android.util.Log.w("SGCOffline", "Arquivo offline ausente no APK", error);
                }
            }
            return new WebResourceResponse("text/plain", "UTF-8", 404, "Not Found", Collections.emptyMap(),
                new java.io.ByteArrayInputStream(new byte[0]));
        }
        return super.shouldInterceptRequest(view, request);
    }

    private boolean isSgcOrigin(Uri uri) {
        return "https".equals(uri.getScheme()) && "sgc.rzin.com.br".equals(uri.getHost())
            && (uri.getPort() == -1 || uri.getPort() == 443);
    }

    private String offlinePage(Uri failedUrl) {
        String path = failedUrl.getPath();
        if (!isSgcOrigin(failedUrl) || path == null) return OFFLINE_PAGE;
        Uri.Builder local = Uri.parse(PROVIDER_OFFLINE_PAGE).buildUpon();
        if (path.equals("/app")) return local.build().toString();
        if (path.equals("/provider-offline.html")) {
            for (String param : new String[]{"tenant", "new", "os"}) {
                String value = failedUrl.getQueryParameter(param);
                if (value != null) local.appendQueryParameter(param, value);
            }
            return local.build().toString();
        }
        Matcher provider = PROVIDER_ROUTE.matcher(path);
        if (!provider.matches()) return OFFLINE_PAGE;
        local.appendQueryParameter("tenant", provider.group(1));
        String action = provider.group(2);
        if ("create".equals(action)) local.appendQueryParameter("new", "1");
        else if (action != null) local.appendQueryParameter("os", action);
        return local.build().toString();
    }

    @Override
    public void onPageStarted(WebView view, String url, Bitmap favicon) {
        super.onPageStarted(view, url, favicon);
        if (isWebUrl(Uri.parse(url))) {
            activity.showNavigationLoading("Carregando…");
        }
    }

    @Override
    public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
        super.onReceivedError(view, request, error);

        if (request.isForMainFrame() && !OFFLINE_PAGE.equals(request.getUrl().toString())) {
            activity.hideNavigationLoading();
            view.loadUrl(offlinePage(request.getUrl()));
        }
    }

    @Override
    public void onPageFinished(WebView view, String url) {
        super.onPageFinished(view, url);
        activity.hideNavigationLoading();
        navigationEngine.completePageLoad(url);
    }

    @Override
    public void doUpdateVisitedHistory(WebView view, String url, boolean isReload) {
        super.doUpdateVisitedHistory(view, url, isReload);

        if (view.getProgress() == 100 && !navigationEngine.isBackNavigationPending()) {
            navigationEngine.recordNavigation(url);
        }
    }
}
