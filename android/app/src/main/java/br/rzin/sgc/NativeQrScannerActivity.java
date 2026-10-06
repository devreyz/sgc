package br.rzin.sgc;

import android.Manifest;
import android.app.Activity;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.graphics.Typeface;
import android.os.Bundle;
import android.view.Gravity;
import android.view.ViewGroup;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.TextView;

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
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.atomic.AtomicBoolean;

public class NativeQrScannerActivity extends ComponentActivity {
    public static final String EXTRA_BATCH = "batch";
    public static final String RESULT_CODES = "codes";
    private final LinkedHashSet<String> codes = new LinkedHashSet<>();
    private final AtomicBoolean processing = new AtomicBoolean(false);
    private PreviewView previewView;
    private TextView countView;
    private TextView listView;
    private boolean batch;
    private int lensFacing = CameraSelector.LENS_FACING_BACK;
    private Camera camera;
    private ExecutorService executor;
    private BarcodeScanner scanner;
    private final ActivityResultLauncher<String> permission = registerForActivityResult(
        new ActivityResultContracts.RequestPermission(), granted -> { if (granted) bindCamera(); else finishCancelled(); }
    );

    @Override protected void onCreate(Bundle state) {
        super.onCreate(state);
        batch = getIntent().getBooleanExtra(EXTRA_BATCH, false);
        executor = Executors.newSingleThreadExecutor();
        scanner = BarcodeScanning.getClient(new BarcodeScannerOptions.Builder()
            .setBarcodeFormats(Barcode.FORMAT_QR_CODE).build());
        buildUi();
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
        Button close = button("Fechar"); close.setOnClickListener(v -> finishCancelled());
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
        if (batch) { Button done = button("Concluir leitura"); done.setEnabled(false); done.setOnClickListener(v -> finishSuccess()); done.setTag("done"); panel.addView(done, new LinearLayout.LayoutParams(-1, dp(48))); }
        FrameLayout.LayoutParams panelParams = new FrameLayout.LayoutParams(-1, -2, Gravity.BOTTOM); root.addView(panel, panelParams);
        setContentView(root);
    }

    private void bindCamera() {
        ListenableFuture<ProcessCameraProvider> future = ProcessCameraProvider.getInstance(this);
        future.addListener(() -> {
            try {
                ProcessCameraProvider provider = future.get(); provider.unbindAll();
                CameraSelector selector = new CameraSelector.Builder().requireLensFacing(lensFacing).build();
                Preview preview = new Preview.Builder().build(); preview.setSurfaceProvider(previewView.getSurfaceProvider());
                ImageAnalysis analysis = new ImageAnalysis.Builder().setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST).build();
                analysis.setAnalyzer(executor, image -> {
                    if (processing.getAndSet(true)) { image.close(); return; }
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
        runOnUiThread(() -> { updatePanel(); if (!batch) finishSuccess(); });
    }

    private void updatePanel() {
        String lens = lensFacing == CameraSelector.LENS_FACING_BACK ? "Traseira 1x" : "Frontal";
        countView.setText(codes.isEmpty() ? lens + " · Aponte para o QR Code" : lens + " · " + codes.size() + " documento(s) lido(s)");
        listView.setText(codes.isEmpty() ? "Os documentos lidos aparecerão aqui." : String.join("\n", codes));
        ViewGroup panel = (ViewGroup) countView.getParent();
        if (panel != null) { android.view.View done = panel.findViewWithTag("done"); if (done != null) done.setEnabled(!codes.isEmpty()); }
    }

    private void finishSuccess() { Intent data = new Intent(); data.putStringArrayListExtra(RESULT_CODES, new ArrayList<>(codes)); setResult(Activity.RESULT_OK, data); finish(); }
    private void finishCancelled() { setResult(Activity.RESULT_CANCELED); finish(); }
    private Button button(String text) { Button view = new Button(this); view.setText(text); view.setTextColor(Color.WHITE); view.setTextSize(12); view.setAllCaps(false); view.setBackgroundColor(Color.rgb(27, 117, 81)); return view; }
    private TextView label(String text, int size, boolean bold) { TextView view = new TextView(this); view.setText(text); view.setTextColor(Color.WHITE); view.setTextSize(size); view.setPadding(0, dp(5), 0, dp(5)); if (bold) view.setTypeface(null, Typeface.BOLD); return view; }
    private int dp(int value) { return Math.round(value * getResources().getDisplayMetrics().density); }
    @Override protected void onDestroy() { if (scanner != null) scanner.close(); if (executor != null) executor.shutdown(); super.onDestroy(); }
}
