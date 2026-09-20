package br.rzin.sgc;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.content.Context;
import android.content.Intent;
import android.os.Bundle;
import android.os.Build;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.WebView;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;

import androidx.activity.OnBackPressedCallback;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsControllerCompat;

import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    private static final String APP_ORIGIN = "https://sgc.rzin.com.br";
    static final String LOGIN_URL = "https://sgc.rzin.com.br/login";
    private static final String APP_PREFERENCES = "sgc_app";
    private static final String ONBOARDING_COMPLETE = "onboarding_complete";
    private final PathNavigationEngine navigationEngine = new PathNavigationEngine();
    private View navigationLoading;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        registerPlugin(NativeAuthPlugin.class);
        registerPlugin(NativeSharePlugin.class);
        registerPlugin(NativeDocumentPlugin.class);
        registerPlugin(NativeNavigationPlugin.class);
        registerPlugin(NativeCameraPlugin.class);
        super.onCreate(savedInstanceState);
        createHighPriorityNotificationChannels();
        getWindow().setStatusBarColor(Color.rgb(17, 92, 66));
        getWindow().setNavigationBarColor(Color.rgb(238, 244, 240));
        WindowCompat.setDecorFitsSystemWindows(getWindow(), true);
        new WindowInsetsControllerCompat(getWindow(), getWindow().getDecorView()).setAppearanceLightStatusBars(false);

        if (getBridge() != null) {
            WebView webView = getBridge().getWebView();
            String userAgent = webView.getSettings().getUserAgentString();
            if (!userAgent.contains("SGCAndroid/")) {
                webView.getSettings().setUserAgentString(userAgent + " SGCAndroid/1.0");
            }

            getBridge().setWebViewClient(new NavigationWebViewClient(getBridge(), navigationEngine, this));

            boolean openedNotification = openNotificationRoute(webView, getIntent());
            if (!openedNotification && savedInstanceState == null && !isOnboardingComplete()) {
                webView.loadUrl("file:///android_asset/onboarding.html");
            }
        }

        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                WebView webView = getBridge() == null ? null : getBridge().getWebView();

                if (webView != null) {
                    if (navigationEngine.isBackNavigationPending()) {
                        return;
                    }

                    String previousUrl = navigationEngine.beginBackNavigation();
                    if (previousUrl != null) {
                        webView.loadUrl(previousUrl);
                        return;
                    }
                }

                setEnabled(false);
                try {
                    getOnBackPressedDispatcher().onBackPressed();
                } finally {
                    setEnabled(true);
                }
            }
        });
    }

    @Override public void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);

        if (getBridge() != null) {
            openNotificationRoute(getBridge().getWebView(), intent);
        }
    }

    void completeOnboarding(WebView webView) {
        getSharedPreferences(APP_PREFERENCES, MODE_PRIVATE)
            .edit()
            .putBoolean(ONBOARDING_COMPLETE, true)
            .apply();
        webView.loadUrl(LOGIN_URL);
    }

    void showNavigationLoading(String message) {
        runOnUiThread(() -> {
            if (isFinishing() || navigationLoading != null) {
                if (navigationLoading instanceof ViewGroup group && group.getChildCount() > 0) {
                    View child = group.getChildAt(0);
                    if (child instanceof TextView text) text.setText(message);
                }
                return;
            }

            FrameLayout host = findViewById(android.R.id.content);
            if (host == null) return;
            FrameLayout overlay = new FrameLayout(this);
            overlay.setClickable(true);
            overlay.setBackgroundColor(Color.argb(42, 6, 37, 25));
            LinearLayout card = new LinearLayout(this);
            card.setOrientation(LinearLayout.VERTICAL);
            card.setGravity(Gravity.CENTER);
            int padding = dp(22);
            card.setPadding(padding, padding, padding, padding);
            GradientDrawable cardBackground = new GradientDrawable();
            cardBackground.setColor(Color.rgb(22, 97, 70));
            cardBackground.setCornerRadius(dp(22));
            card.setBackground(cardBackground);
            card.setElevation(dp(12));

            ProgressBar spinner = new ProgressBar(this);
            spinner.getIndeterminateDrawable().setColorFilter(Color.WHITE, android.graphics.PorterDuff.Mode.SRC_IN);
            LinearLayout.LayoutParams spinnerParams = new LinearLayout.LayoutParams(dp(34), dp(34));
            spinnerParams.gravity = Gravity.CENTER_HORIZONTAL;
            card.addView(spinner, spinnerParams);
            TextView label = new TextView(this);
            label.setText(message);
            label.setTextColor(Color.WHITE);
            label.setTextSize(14);
            label.setTypeface(null, Typeface.BOLD);
            label.setGravity(Gravity.CENTER);
            LinearLayout.LayoutParams labelParams = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT);
            labelParams.topMargin = dp(10);
            card.addView(label, labelParams);

            FrameLayout.LayoutParams cardParams = new FrameLayout.LayoutParams(dp(184), ViewGroup.LayoutParams.WRAP_CONTENT, Gravity.CENTER);
            overlay.addView(card, cardParams);
            overlay.setAlpha(0f);
            host.addView(overlay, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
            overlay.animate().alpha(1f).setDuration(140).start();
            navigationLoading = overlay;
        });
    }

    void hideNavigationLoading() {
        runOnUiThread(() -> {
            View overlay = navigationLoading;
            navigationLoading = null;
            if (overlay == null) return;
            overlay.animate().alpha(0f).setDuration(140).withEndAction(() -> {
                if (overlay.getParent() instanceof ViewGroup host) host.removeView(overlay);
            }).start();
        });
    }

    @Override public void onResume() {
        super.onResume();
        // Ao retornar do visualizador de PDF, nenhuma tela de carregamento
        // pode permanecer por cima do WebView.
        hideNavigationLoading();
    }

    private int dp(int value) {
        return Math.round(value * getResources().getDisplayMetrics().density);
    }

    private boolean openNotificationRoute(WebView webView, Intent intent) {
        if (webView == null || intent == null) return false;

        String route = intent.getStringExtra("route");
        if (route == null) route = intent.getStringExtra("notification_route");
        if (route == null || !route.matches("^/[A-Za-z0-9_-]+/notifications/[0-9a-fA-F-]{36}/open$")) return false;

        intent.removeExtra("route");
        intent.removeExtra("notification_route");
        showNavigationLoading("Abrindo notificação...");
        webView.loadUrl(APP_ORIGIN + route);

        return true;
    }

    private void createHighPriorityNotificationChannels() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return;

        NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        if (manager == null) return;

        createHighPriorityChannel(manager, "general_high_v2", "Geral", "Avisos gerais do SGC");
        createHighPriorityChannel(manager, "operations_high_v2", "Operações", "Entregas, estoque e operações");
        createHighPriorityChannel(manager, "documents_high_v2", "Documentos", "Comprovantes e documentos");
        createHighPriorityChannel(manager, "financial_high_v2", "Financeiro", "Atualizações financeiras importantes");
    }

    private void createHighPriorityChannel(NotificationManager manager, String id, String name, String description) {
        NotificationChannel channel = new NotificationChannel(id, name, NotificationManager.IMPORTANCE_HIGH);
        channel.setDescription(description);
        channel.enableVibration(true);
        channel.enableLights(true);
        channel.setLightColor(Color.rgb(17, 92, 66));
        channel.setShowBadge(true);
        channel.setLockscreenVisibility(Notification.VISIBILITY_PRIVATE);
        manager.createNotificationChannel(channel);
    }

    private boolean isOnboardingComplete() {
        return getSharedPreferences(APP_PREFERENCES, MODE_PRIVATE)
            .getBoolean(ONBOARDING_COMPLETE, false);
    }
}
