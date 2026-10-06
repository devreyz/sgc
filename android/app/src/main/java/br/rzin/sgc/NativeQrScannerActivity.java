package br.rzin.sgc;

import android.Manifest;
import android.app.Activity;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.graphics.Typeface;
import android.media.AudioManager;
import android.media.ToneGenerator;
import android.os.Bundle;
import android.util.Size;
import android.view.Gravity;
import android.view.ViewGroup;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.webkit.CookieManager;

import androidx.activity.ComponentActivity;
import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.camera.core.Camera;
import androidx.camera.core.CameraSelector;
import androidx.camera.core.ImageAnalysis;
import androidx.camera.core.Preview;
import androidx.camera.lifecycle.ProcessCameraProvider;
import androidx.camera.view.PreviewView;
import androidx.core.content.ContextCompat;

import com.google.common.util.concurrent.ListenableFuture;
import com.google.mlkit.vision.barcode.BarcodeScanner;
import com.google.mlkit.vision.barcode.BarcodeScannerOptions;
import com.google.mlkit.vision.barcode.BarcodeScanning;
import com.google.mlkit.vision.barcode.common.Barcode;
import com.google.mlkit.vision.common.InputImage;

import java.util.ArrayList;
import java.util.LinkedHashSet;
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.atomic.AtomicBoolean;
import org.json.JSONArray;
import org.json.JSONObject;

public class NativeQrScannerActivity extends ComponentActivity {
    public static final String EXTRA_BATCH = "batch";
    public static final String EXTRA_VERIFICATION_URL = "verificationUrl";
    public static final String EXTRA_CSRF_TOKEN = "csrfToken";
    public static final String EXTRA_SELECTION_PAYLOAD = "selectionPayload";
    public static final String RESULT_CODES = "codes";
    private final LinkedHashSet<String> codes = new LinkedHashSet<>();
    private final AtomicBoolean processing = new AtomicBoolean(false);
    private final AtomicBoolean reportOpen = new AtomicBoolean(false);
    private PreviewView previewView;
    private TextView countView;
    private TextView listView;
    private boolean batch;
    private String verificationUrl;
    private String csrfToken;
    private String selectionPayload;
    private String pendingCode;
    private TextView reportView;
    private Button scanAnotherButton;
    private Button finishButton;
    private int lensFacing = CameraSelector.LENS_FACING_BACK;
    private Camera camera;
    private ExecutorService executor;
    private BarcodeScanner scanner;
    private ToneGenerator toneGenerator;
    private final ActivityResultLauncher<String> permission = registerForActivityResult(
        new ActivityResultContracts.RequestPermission(), granted -> { if (granted) bindCamera(); else finishCancelled(); }
    );

    @Override protected void onCreate(Bundle state) {
        super.onCreate(state);
        batch = getIntent().getBooleanExtra(EXTRA_BATCH, false);
        verificationUrl = getIntent().getStringExtra(EXTRA_VERIFICATION_URL);
        csrfToken = getIntent().getStringExtra(EXTRA_CSRF_TOKEN);
        selectionPayload = getIntent().getStringExtra(EXTRA_SELECTION_PAYLOAD);
        executor = Executors.newSingleThreadExecutor();
        scanner = BarcodeScanning.getClient(new BarcodeScannerOptions.Builder()
            .setBarcodeFormats(Barcode.FORMAT_QR_CODE).build());
        toneGenerator = new ToneGenerator(AudioManager.STREAM_NOTIFICATION, 80);
        buildUi();
        getOnBackPressedDispatcher().addCallback(this, new androidx.activity.OnBackPressedCallback(true) {
            @Override public void handleOnBackPressed() { finishWithLastOrCancel(); }
        });
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) bindCamera();
        else permission.launch(Manifest.permission.CAMERA);
    }

    private void buildUi() {
        FrameLayout root = new FrameLayout(this);
        root.setBackgroundColor(Color.BLACK);
        previewView = new PreviewView(this);
        previewView.setScaleType(PreviewView.ScaleType.FILL_CENTER);
        root.addView(previewView, new FrameLayout.LayoutParams(-1, -1));

        LinearLayout top = new LinearLayout(this);
        top.setGravity(Gravity.CENTER_VERTICAL);
        top.setPadding(dp(16), dp(12), dp(16), dp(12));
        top.setBackgroundColor(Color.argb(190, 7, 31, 22));
        Button close = button("Fechar"); close.setOnClickListener(v -> finishWithLastOrCancel());
        TextView title = label(batch ? "Leitura em lote" : "Ler QR Code", 18, true);
        LinearLayout.LayoutParams titleParams = new LinearLayout.LayoutParams(0, -2, 1); titleParams.leftMargin = dp(12);
        top.addView(close); top.addView(title, titleParams);
        Button lens = button("Trocar lente"); lens.setOnClickListener(v -> { lensFacing = lensFacing == CameraSelector.LENS_FACING_BACK ? CameraSelector.LENS_FACING_FRONT : CameraSelector.LENS_FACING_BACK; bindCamera(); });
        top.addView(lens);
        root.addView(top, new FrameLayout.LayoutParams(-1, -2, Gravity.TOP));

        LinearLayout panel = new LinearLayout(this);
        panel.setOrientation(LinearLayout.VERTICAL); panel.setPadding(dp(16), dp(12), dp(16), dp(16));
        panel.setBackgroundColor(Color.argb(225, 7, 31, 22));
        countView = label("Traseira 1x · Aponte para o QR Code", 14, true);
        listView = label("Os documentos lidos aparecerão aqui.", 12, false); listView.setMaxLines(4);
        panel.addView(countView); panel.addView(listView);
        if (batch && !hasNativeVerification()) { Button done = button("Concluir leitura"); done.setEnabled(false); done.setOnClickListener(v -> finishSuccess()); done.setTag("done"); panel.addView(done, new LinearLayout.LayoutParams(-1, dp(48))); }
        if (hasNativeVerification()) {
            reportView = label("", 13, false); reportView.setVisibility(android.view.View.GONE); reportView.setMaxLines(12); panel.addView(reportView);
            LinearLayout actions = new LinearLayout(this); actions.setPadding(0, dp(8), 0, 0);
            scanAnotherButton = button(hasNativeSelection() ? "Escanear próximo" : "Escanear outro"); scanAnotherButton.setVisibility(android.view.View.GONE);
            scanAnotherButton.setOnClickListener(v -> resetVerification());
            finishButton = button(hasNativeSelection() ? "Concluir e adicionar" : "Concluir"); finishButton.setVisibility(android.view.View.GONE);
            finishButton.setOnClickListener(v -> finishCompleted());
            actions.addView(scanAnotherButton, new LinearLayout.LayoutParams(0, dp(48), 1));
            LinearLayout.LayoutParams finishParams = new LinearLayout.LayoutParams(0, dp(48), 1); finishParams.leftMargin = dp(8); actions.addView(finishButton, finishParams);
            panel.addView(actions);
        }
        FrameLayout.LayoutParams panelParams = new FrameLayout.LayoutParams(-1, -2, Gravity.BOTTOM); root.addView(panel, panelParams);
        setContentView(root);
    }

    private void bindCamera() {
        ListenableFuture<ProcessCameraProvider> future = ProcessCameraProvider.getInstance(this);
        future.addListener(() -> {
            try {
                ProcessCameraProvider provider = future.get(); provider.unbindAll();
                CameraSelector selector = new CameraSelector.Builder().requireLensFacing(lensFacing).build();
                Preview preview = new Preview.Builder().setTargetResolution(new Size(1920, 1080)).build();
                preview.setSurfaceProvider(previewView.getSurfaceProvider());
                ImageAnalysis analysis = new ImageAnalysis.Builder()
                    .setTargetResolution(new Size(1920, 1080))
                    .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST).build();
                analysis.setAnalyzer(executor, image -> {
                    if (reportOpen.get() || processing.getAndSet(true)) { image.close(); return; }
                    InputImage input = InputImage.fromMediaImage(image.getImage(), image.getImageInfo().getRotationDegrees());
                    scanner.process(input).addOnSuccessListener(found -> {
                        for (Barcode barcode : found) { String raw = barcode.getRawValue(); if (raw != null && !raw.trim().isEmpty()) { onCode(raw.trim()); break; } }
                    }).addOnCompleteListener(task -> { image.close(); processing.set(false); });
                });
                camera = provider.bindToLifecycle(this, selector, preview, analysis);
                runOnUiThread(this::updatePanel);
            } catch (Exception exception) { runOnUiThread(() -> countView.setText("Não foi possível iniciar esta lente.")); }
        }, ContextCompat.getMainExecutor(this));
    }

    private void onCode(String code) {
        if (!codes.add(code)) return;
        pendingCode = code;
        if (hasNativeVerification()) {
            reportOpen.set(true);
            runOnUiThread(() -> { countView.setText("Conferindo documento…"); listView.setText(code); });
            executor.execute(() -> verifyDocument(code));
            return;
        }
        runOnUiThread(() -> { updatePanel(); if (!batch) finishSuccess(); });
    }

    private boolean hasNativeVerification() { return verificationUrl != null && !verificationUrl.isBlank(); }
    private boolean hasNativeSelection() { return batch && selectionPayload != null && !selectionPayload.isBlank(); }

    private void verifyDocument(String code) {
        HttpURLConnection connection = null;
        try {
            URL url = new URL(verificationUrl);
            connection = (HttpURLConnection) url.openConnection();
            connection.setRequestMethod("POST"); connection.setConnectTimeout(10000); connection.setReadTimeout(15000); connection.setDoOutput(true);
            connection.setRequestProperty("Accept", "application/json"); connection.setRequestProperty("Content-Type", "application/json; charset=utf-8");
            if (csrfToken != null && !csrfToken.isBlank()) connection.setRequestProperty("X-CSRF-TOKEN", csrfToken);
            String cookie = CookieManager.getInstance().getCookie(verificationUrl); if (cookie != null) connection.setRequestProperty("Cookie", cookie);
            JSONObject request = hasNativeSelection() ? new JSONObject(selectionPayload) : new JSONObject();
            if (hasNativeSelection()) {
                request.put("mode", "receipts");
                request.put("receipt_codes", new JSONArray().put(code));
                request.put("distribution_ids", new JSONArray());
            } else request.put("code", code);
            byte[] body = request.toString().getBytes(StandardCharsets.UTF_8);
            connection.setFixedLengthStreamingMode(body.length); try (OutputStream output = connection.getOutputStream()) { output.write(body); }
            java.io.InputStream input = connection.getResponseCode() >= 400 ? connection.getErrorStream() : connection.getInputStream();
            StringBuilder json = new StringBuilder(); try (BufferedReader reader = new BufferedReader(new InputStreamReader(input, StandardCharsets.UTF_8))) { String line; while ((line = reader.readLine()) != null) json.append(line); }
            JSONObject result = new JSONObject(json.toString());
            runOnUiThread(() -> { if (hasNativeSelection()) showSelection(result); else showVerification(result); });
        } catch (Exception exception) {
            runOnUiThread(() -> {
                if (hasNativeSelection()) rejectPendingCode();
                showVerificationError("Não foi possível consultar o documento. Confira a conexão e tente novamente.");
            });
        } finally { if (connection != null) connection.disconnect(); }
    }

    private void showSelection(JSONObject result) {
        JSONArray batches = result.optJSONArray("batches");
        JSONObject selectedBatch = batches == null ? null : batches.optJSONObject(0);
        if (selectedBatch == null || !selectedBatch.optBoolean("receipt_found", false)) {
            rejectPendingCode();
            showVerificationError("Comprovante não encontrado ou incompatível com este faturamento. Aponte para outro QR Code.");
            return;
        }

        JSONArray documents = selectedBatch.optJSONArray("documents");
        JSONObject document = documents == null ? null : documents.optJSONObject(0);
        int selectedCount = selectedBatch.optInt("selected_count", 0);
        int excludedCount = selectedBatch.optInt("excluded_count", 0);
        if (selectedCount <= 0) rejectPendingCode();
        countView.setText(selectedCount > 0 ? "Lote selecionado automaticamente" : "Comprovante sem entregas elegíveis");
        countView.setTextColor(selectedCount > 0 ? Color.rgb(132, 255, 184) : Color.rgb(255, 210, 91));

        StringBuilder text = new StringBuilder();
        if (document != null) {
            text.append(document.optString("number", "Comprovante"));
            if (!document.optString("associate", "").isBlank()) text.append(" · ").append(document.optString("associate"));
            JSONArray distributions = document.optJSONArray("distributions");
            text.append("\n\n").append(selectedCount).append(" distribuição(ões) pronta(s) para adicionar");
            if (excludedCount > 0) text.append(" · ").append(excludedCount).append(" não elegível(is)");
            if (distributions != null) {
                for (int index = 0; index < Math.min(distributions.length(), 5); index++) {
                    JSONObject row = distributions.optJSONObject(index);
                    if (row == null) continue;
                    text.append("\n• ").append(row.optString("date", ""));
                    text.append(" · ").append(row.optString("product", "Produto"));
                    text.append(" · ").append(row.optString("quantity", "0")).append(" ").append(row.optString("unit", "un"));
                }
                if (distributions.length() > 5) text.append("\n+ ").append(distributions.length() - 5).append(" distribuição(ões)");
            }
        } else {
            text.append(selectedCount).append(" distribuição(ões) pronta(s) para adicionar.");
        }
        listView.setVisibility(android.view.View.GONE);
        reportView.setText(text.toString()); reportView.setVisibility(android.view.View.VISIBLE);
        scanAnotherButton.setVisibility(android.view.View.VISIBLE); finishButton.setVisibility(android.view.View.VISIBLE);
        if (selectedCount > 0) playSuccessTone();
    }

    private void showVerification(JSONObject result) {
        String verdict = result.optString("verdict", "invalid");
        int color = verdict.equals("valid") ? Color.rgb(132, 255, 184) : verdict.equals("attention") ? Color.rgb(255, 210, 91) : Color.rgb(255, 125, 135);
        countView.setText(result.optString("headline", "Resultado da conferência")); countView.setTextColor(color);
        StringBuilder text = new StringBuilder(result.optString("message", ""));
        JSONObject document = result.optJSONObject("document");
        if (document != null) {
            text.append("\n\n").append(document.optString("number", "Documento"));
            if (!document.optString("party", "").isBlank()) text.append(" · ").append(document.optString("party"));
            text.append("\n").append(document.optInt("distribution_count", 0)).append(" distribuição(ões)");
        }
        JSONArray issues = result.optJSONArray("issues");
        if (issues != null && issues.length() > 0) {
            text.append("\n\nPontos encontrados:");
            for (int index = 0; index < Math.min(issues.length(), 5); index++) text.append("\n• ").append(issues.optJSONObject(index).optString("message"));
        } else text.append("\n\n✓ Nenhuma inconsistência conhecida.");
        listView.setVisibility(android.view.View.GONE); reportView.setText(text.toString()); reportView.setVisibility(android.view.View.VISIBLE);
        scanAnotherButton.setVisibility(android.view.View.VISIBLE); finishButton.setVisibility(android.view.View.VISIBLE);
    }

    private void showVerificationError(String message) {
        countView.setText("Falha na conferência"); countView.setTextColor(Color.rgb(255, 125, 135));
        listView.setVisibility(android.view.View.GONE); reportView.setText(message); reportView.setVisibility(android.view.View.VISIBLE);
        scanAnotherButton.setVisibility(android.view.View.VISIBLE); finishButton.setVisibility(android.view.View.VISIBLE);
    }

    private void rejectPendingCode() {
        if (pendingCode != null) codes.remove(pendingCode);
        pendingCode = null;
    }

    private void resetVerification() {
        if (!hasNativeSelection()) codes.clear();
        pendingCode = null; reportOpen.set(false); countView.setTextColor(Color.WHITE); reportView.setVisibility(android.view.View.GONE);
        scanAnotherButton.setVisibility(android.view.View.GONE); finishButton.setVisibility(android.view.View.GONE);
        listView.setVisibility(android.view.View.VISIBLE); listView.setText("Aponte para o próximo QR Code."); updatePanel();
    }

    private void updatePanel() {
        String lens = lensFacing == CameraSelector.LENS_FACING_BACK ? "Traseira 1x" : "Frontal";
        countView.setText(codes.isEmpty() ? lens + " · Aponte para o QR Code" : lens + " · " + codes.size() + " documento(s) lido(s)");
        listView.setText(codes.isEmpty() ? "Os documentos lidos aparecerão aqui." : String.join("\n", codes));
        ViewGroup panel = (ViewGroup) countView.getParent();
        if (panel != null) { android.view.View done = panel.findViewWithTag("done"); if (done != null) done.setEnabled(!codes.isEmpty()); }
    }

    private void finishSuccess() { Intent data = new Intent(); data.putStringArrayListExtra(RESULT_CODES, new ArrayList<>(codes)); setResult(Activity.RESULT_OK, data); finish(); }
    private void finishCompleted() {
        countView.setText(codes.isEmpty() ? "Leitura encerrada" : "✓ Leitura concluída · " + codes.size() + " documento(s)");
        countView.setTextColor(Color.rgb(132, 255, 184));
        playSuccessTone();
        countView.postDelayed(this::finishSuccess, 450);
    }
    private void playSuccessTone() { if (toneGenerator != null) toneGenerator.startTone(ToneGenerator.TONE_PROP_ACK, 160); }
    private void finishWithLastOrCancel() { if (codes.isEmpty()) finishCancelled(); else finishSuccess(); }
    private void finishCancelled() { setResult(Activity.RESULT_CANCELED); finish(); }
    private Button button(String text) { Button view = new Button(this); view.setText(text); view.setTextColor(Color.WHITE); view.setTextSize(12); view.setAllCaps(false); view.setBackgroundColor(Color.rgb(27, 117, 81)); return view; }
    private TextView label(String text, int size, boolean bold) { TextView view = new TextView(this); view.setText(text); view.setTextColor(Color.WHITE); view.setTextSize(size); view.setPadding(0, dp(5), 0, dp(5)); if (bold) view.setTypeface(null, Typeface.BOLD); return view; }
    private int dp(int value) { return Math.round(value * getResources().getDisplayMetrics().density); }
    @Override protected void onDestroy() { if (scanner != null) scanner.close(); if (toneGenerator != null) toneGenerator.release(); if (executor != null) executor.shutdown(); super.onDestroy(); }
}
