package br.rzin.sgc;

import android.net.Uri;
import android.graphics.Bitmap;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebView;

import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeWebViewClient;

final class NavigationWebViewClient extends BridgeWebViewClient {
    private static final String OFFLINE_PAGE = "file:///android_asset/offline.html";
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
            view.loadUrl(OFFLINE_PAGE);
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
