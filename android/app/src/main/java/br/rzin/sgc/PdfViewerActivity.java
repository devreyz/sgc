package br.rzin.sgc;

import android.app.Activity;
import android.content.Intent;
import android.graphics.Bitmap;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.graphics.pdf.PdfRenderer;
import android.content.ContentValues;
import android.os.Build;
import android.provider.MediaStore;
import android.os.Bundle;
import android.os.ParcelFileDescriptor;
import android.os.CancellationSignal;
import android.print.PrintAttributes;
import android.print.PrintDocumentAdapter;
import android.print.PrintDocumentInfo;
import android.print.PrintManager;
import android.view.Gravity;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.HorizontalScrollView;
import android.widget.TextView;

import java.io.File;
import java.io.FileInputStream;
import java.io.OutputStream;
import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.atomic.AtomicInteger;

import androidx.core.content.FileProvider;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.appcompat.widget.AppCompatImageView;

public class PdfViewerActivity extends Activity {
    static final String EXTRA_FILE_PATH = "file_path";
    static final String EXTRA_TITLE = "title";
    static final String EXTRA_RELATIVE_PATH = "relative_path";
    static final String EXTRA_ORIGIN = "origin";
    private static final int COLOR_PRIMARY = Color.rgb(22, 91, 66);
    private static final int COLOR_PRIMARY_DARK = Color.rgb(17, 63, 49);
    private static final int COLOR_SURFACE = Color.rgb(250, 253, 251);
    private static final int COLOR_CANVAS = Color.rgb(238, 244, 240);
    private final ExecutorService executor = Executors.newSingleThreadExecutor();
    private LinearLayout pages;
    private ProgressBar progress;
    private int basePageWidth;
    private int baseContentWidth;
    private String pdfPath;
    private PdfScrollView viewport;
    private final AtomicInteger qualityGeneration = new AtomicInteger();

    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        WindowCompat.setDecorFitsSystemWindows(getWindow(), false);
        getWindow().setStatusBarColor(COLOR_PRIMARY_DARK);
        getWindow().setNavigationBarColor(COLOR_CANVAS);
        LinearLayout root = new LinearLayout(this); root.setOrientation(LinearLayout.VERTICAL); root.setBackgroundColor(COLOR_CANVAS);
        LinearLayout header = new LinearLayout(this); header.setOrientation(LinearLayout.VERTICAL); header.setPadding(dp(20), dp(12), dp(20), dp(12)); header.setBackgroundColor(COLOR_PRIMARY); header.setElevation(dp(3));
        TextView title = new TextView(this); title.setText(getIntent().getStringExtra(EXTRA_TITLE)); title.setTextSize(18); title.setTypeface(null, Typeface.BOLD); title.setTextColor(Color.WHITE); title.setSingleLine(true); title.setEllipsize(android.text.TextUtils.TruncateAt.END);
        header.addView(title, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));

        if ("google_drive".equals(getIntent().getStringExtra(EXTRA_ORIGIN))) {
            LinearLayout origin = new LinearLayout(this); origin.setGravity(Gravity.CENTER_VERTICAL); origin.setPadding(0, dp(7), 0, 0);
            AppCompatImageView driveIcon = new AppCompatImageView(this); driveIcon.setImageResource(R.drawable.ic_google_drive); driveIcon.setContentDescription("Google Drive");
            origin.addView(driveIcon, new LinearLayout.LayoutParams(dp(16), dp(16)));
            TextView originLabel = new TextView(this); originLabel.setText("Disponível no Google Drive"); originLabel.setTextSize(12); originLabel.setTextColor(Color.rgb(223, 241, 229)); originLabel.setPadding(dp(6), 0, 0, 0);
            origin.addView(originLabel);
            header.addView(origin);
        }
        HorizontalScrollView actionScroll = new HorizontalScrollView(this); actionScroll.setHorizontalScrollBarEnabled(false); actionScroll.setFillViewport(true);
        LinearLayout actions = new LinearLayout(this); actions.setGravity(Gravity.CENTER_VERTICAL); actions.setPadding(0, dp(12), 0, 0);
        LinearLayout download = action("Baixar", R.drawable.ic_pdf_download, false); download.setOnClickListener(v -> saveCopy()); actions.addView(download);
        LinearLayout share = action("Compartilhar", R.drawable.ic_pdf_share, false); share.setOnClickListener(v -> sharePdf()); actions.addView(share);
        LinearLayout print = action("Imprimir", R.drawable.ic_pdf_print, false); print.setOnClickListener(v -> printPdf()); actions.addView(print);
        LinearLayout close = action("Fechar", R.drawable.ic_pdf_close, true); close.setOnClickListener(v -> finish()); actions.addView(close);
        actionScroll.addView(actions, new HorizontalScrollView.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        header.addView(actionScroll);
        root.addView(header);
        progress = new ProgressBar(this); LinearLayout.LayoutParams loading = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT); loading.gravity = Gravity.CENTER; loading.topMargin = 36; root.addView(progress, loading);
        basePageWidth = Math.max(getResources().getDisplayMetrics().widthPixels - dp(28), dp(280));
        baseContentWidth = getResources().getDisplayMetrics().widthPixels;
        viewport = new PdfScrollView(this);
        pages = new LinearLayout(this); pages.setOrientation(LinearLayout.VERTICAL); pages.setGravity(Gravity.CENTER_HORIZONTAL); pages.setPadding(0, dp(12), 0, dp(32));
        viewport.addView(pages, new PdfScrollView.LayoutParams(baseContentWidth, ViewGroup.LayoutParams.WRAP_CONTENT));
        viewport.setZoomListener(new PdfScrollView.ZoomListener() {
            @Override public void onZoomChanged(float zoom) { applyDocumentZoom(zoom); }
            @Override public void onGestureSettled(float zoom) { renderVisibleQuality(zoom); }
        });
        root.addView(viewport, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, 0, 1));
        setContentView(root);
        ViewCompat.setOnApplyWindowInsetsListener(root, (view, windowInsets) -> {
            Insets bars = windowInsets.getInsets(WindowInsetsCompat.Type.systemBars());
            header.setPadding(dp(20), bars.top + dp(10), dp(20), dp(12));
            view.setPadding(0, 0, 0, bars.bottom);
            return windowInsets;
        });
        ViewCompat.requestApplyInsets(root);
        pdfPath = getIntent().getStringExtra(EXTRA_FILE_PATH);
        render(pdfPath);
    }

    private LinearLayout action(String label, int icon, boolean emphasized) {
        LinearLayout button = new LinearLayout(this); button.setGravity(Gravity.CENTER); button.setOrientation(LinearLayout.HORIZONTAL); button.setClickable(true); button.setFocusable(true); button.setContentDescription(label); button.setPadding(dp(12), dp(10), dp(12), dp(10));
        GradientDrawable background = new GradientDrawable(); background.setCornerRadius(dp(12)); background.setColor(emphasized ? Color.WHITE : Color.rgb(41, 113, 85)); background.setStroke(dp(1), emphasized ? Color.WHITE : Color.rgb(135, 187, 162)); button.setBackground(background);
        AppCompatImageView image = new AppCompatImageView(this); image.setImageResource(icon); image.setImageTintList(android.content.res.ColorStateList.valueOf(emphasized ? COLOR_PRIMARY : Color.WHITE));
        button.addView(image, new LinearLayout.LayoutParams(dp(18), dp(18)));
        TextView text = new TextView(this); text.setText(label); text.setTextSize(12); text.setTypeface(null, Typeface.BOLD); text.setTextColor(emphasized ? COLOR_PRIMARY : Color.WHITE); text.setPadding(dp(6), 0, 0, 0); button.addView(text);
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT); params.setMargins(0, 0, dp(8), 0); button.setLayoutParams(params);
        return button;
    }

    private int dp(int value) { return Math.round(value * getResources().getDisplayMetrics().density); }

    private void applyDocumentZoom(float zoom) {
        int pageWidth = Math.round(basePageWidth * zoom);
        for (int index = 0; index < pages.getChildCount(); index++) {
            android.view.View child = pages.getChildAt(index);
            if (!(child instanceof AppCompatImageView) || !(child.getTag() instanceof PageSize size)) continue;
            child.setLayoutParams(new LinearLayout.LayoutParams(pageWidth, Math.round(size.height * zoom)));
        }
        ViewGroup.LayoutParams pageParams = pages.getLayoutParams();
        pageParams.width = Math.round(baseContentWidth * zoom);
        pages.setLayoutParams(pageParams);
        pages.requestLayout();
        viewport.requestLayout();
    }

    private void renderVisibleQuality(float zoom) {
        if (zoom <= 1.05f || pdfPath == null || pages.getChildCount() == 0) return;
        int targetWidth = Math.min(2800, Math.max(basePageWidth, Math.round(basePageWidth * zoom * 1.1f)));
        int top = viewport.getScrollY() - viewport.getHeight() / 2;
        int bottom = viewport.getScrollY() + viewport.getHeight() + viewport.getHeight() / 2;
        List<PageTarget> targets = new ArrayList<>();
        for (int index = 0; index < pages.getChildCount(); index++) {
            android.view.View child = pages.getChildAt(index);
            if (!(child instanceof AppCompatImageView view) || !(child.getTag() instanceof PageSize size)) continue;
            if (view.getBottom() < top || view.getTop() > bottom || size.qualityWidth >= targetWidth * 0.9f) continue;
            targets.add(new PageTarget(view, size, targetWidth));
        }
        if (targets.isEmpty()) return;
        int generation = qualityGeneration.incrementAndGet();
        executor.execute(() -> rerenderTargets(targets, generation));
    }

    private void rerenderTargets(List<PageTarget> targets, int generation) {
        try (ParcelFileDescriptor descriptor = ParcelFileDescriptor.open(new File(pdfPath), ParcelFileDescriptor.MODE_READ_ONLY); PdfRenderer renderer = new PdfRenderer(descriptor)) {
            for (PageTarget target : targets) {
                if (generation != qualityGeneration.get() || target.size.pageIndex >= renderer.getPageCount()) return;
                PdfRenderer.Page page = renderer.openPage(target.size.pageIndex);
                int height = Math.round((float) page.getHeight() * target.width / page.getWidth());
                Bitmap bitmap = Bitmap.createBitmap(target.width, height, Bitmap.Config.ARGB_8888);
                page.render(bitmap, null, null, PdfRenderer.Page.RENDER_MODE_FOR_DISPLAY);
                page.close();
                runOnUiThread(() -> {
                    if (generation != qualityGeneration.get() || isFinishing() || isDestroyed()) { bitmap.recycle(); return; }
                    target.view.setImageBitmap(bitmap);
                    target.size.qualityWidth = target.width;
                });
            }
        } catch (Exception ignored) {
            // Mantém o bitmap-base se uma atualização de qualidade falhar.
        }
    }

    private void render(String path) {
        executor.execute(() -> {
            try (ParcelFileDescriptor descriptor = ParcelFileDescriptor.open(new File(path), ParcelFileDescriptor.MODE_READ_ONLY); PdfRenderer renderer = new PdfRenderer(descriptor)) {
                int width = Math.max(basePageWidth, 720);
                for (int index = 0; index < renderer.getPageCount(); index++) {
                    PdfRenderer.Page page = renderer.openPage(index);
                    int height = Math.round((float) page.getHeight() * width / page.getWidth());
                    Bitmap bitmap = Bitmap.createBitmap(width, height, Bitmap.Config.ARGB_8888);
                    page.render(bitmap, null, null, PdfRenderer.Page.RENDER_MODE_FOR_DISPLAY); page.close();
                    final Bitmap image = bitmap; final int pageNumber = index + 1;
                    final int qualityWidth = width;
                    runOnUiThread(() -> addPage(image, pageNumber, qualityWidth));
                }
            } catch (Exception exception) {
                runOnUiThread(() -> { TextView error = new TextView(this); error.setText("Não foi possível abrir este PDF."); error.setTextColor(Color.rgb(185, 28, 28)); error.setPadding(24, 36, 24, 24); pages.addView(error); });
            } finally { runOnUiThread(() -> progress.setVisibility(android.view.View.GONE)); }
        });
    }

    private void addPage(Bitmap bitmap, int number, int qualityWidth) {
        TextView label = new TextView(this); label.setText("Página " + number); label.setTextSize(12); label.setTypeface(null, Typeface.BOLD); label.setPadding(dp(6), dp(14), dp(6), dp(7)); label.setTextColor(Color.rgb(83, 104, 92)); pages.addView(label);
        int baseHeight = Math.round((float) bitmap.getHeight() * basePageWidth / bitmap.getWidth());
        AppCompatImageView view = new AppCompatImageView(this); view.setImageBitmap(bitmap); view.setScaleType(android.widget.ImageView.ScaleType.FIT_XY); view.setBackgroundColor(Color.WHITE); view.setElevation(dp(1)); view.setTag(new PageSize(number - 1, baseHeight, qualityWidth)); pages.addView(view, new LinearLayout.LayoutParams(basePageWidth, baseHeight));
    }
    private static final class PageSize {
        final int pageIndex;
        final int height;
        int qualityWidth;
        PageSize(int pageIndex, int height, int qualityWidth) { this.pageIndex = pageIndex; this.height = height; this.qualityWidth = qualityWidth; }
    }
    private record PageTarget(AppCompatImageView view, PageSize size, int width) {}
    private File pdfFile() { return new File(getIntent().getStringExtra(EXTRA_FILE_PATH)); }
    private void sharePdf() {
        try {
            android.net.Uri uri = FileProvider.getUriForFile(this, getPackageName() + ".fileprovider", pdfFile());
            Intent share = new Intent(Intent.ACTION_SEND); share.setType("application/pdf"); share.putExtra(Intent.EXTRA_STREAM, uri); share.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
            startActivity(Intent.createChooser(share, "Compartilhar PDF"));
        } catch (Exception error) { android.widget.Toast.makeText(this, "Não foi possível compartilhar o PDF.", android.widget.Toast.LENGTH_LONG).show(); }
    }
    private void printPdf() {
        PrintManager manager = (PrintManager) getSystemService(PRINT_SERVICE);
        if (manager == null) { android.widget.Toast.makeText(this, "Impressão não disponível.", android.widget.Toast.LENGTH_LONG).show(); return; }
        manager.print(getIntent().getStringExtra(EXTRA_TITLE), new PrintDocumentAdapter() {
            @Override public void onLayout(PrintAttributes oldAttrs, PrintAttributes newAttrs, CancellationSignal signal, LayoutResultCallback callback, Bundle extras) {
                callback.onLayoutFinished(new PrintDocumentInfo.Builder(pdfFile().getName()).setContentType(PrintDocumentInfo.CONTENT_TYPE_DOCUMENT).setPageCount(PrintDocumentInfo.PAGE_COUNT_UNKNOWN).build(), true);
            }
            @Override public void onWrite(android.print.PageRange[] pages, ParcelFileDescriptor destination, CancellationSignal signal, WriteResultCallback callback) {
                try (FileInputStream input = new FileInputStream(pdfFile()); OutputStream output = new ParcelFileDescriptor.AutoCloseOutputStream(destination)) {
                    byte[] buffer = new byte[8192]; int count; while ((count = input.read(buffer)) > 0 && !signal.isCanceled()) output.write(buffer, 0, count);
                    if (signal.isCanceled()) callback.onWriteCancelled(); else callback.onWriteFinished(new android.print.PageRange[]{android.print.PageRange.ALL_PAGES});
                } catch (Exception error) { callback.onWriteFailed("Não foi possível preparar o PDF para impressão."); }
            }
        }, new PrintAttributes.Builder().build());
    }
    private void saveCopy() {
        String path = getIntent().getStringExtra(EXTRA_FILE_PATH);
        try {
            ContentValues values = new ContentValues();
            values.put(MediaStore.Downloads.DISPLAY_NAME, new File(path).getName()); values.put(MediaStore.Downloads.MIME_TYPE, "application/pdf");
            String relativePath = getIntent().getStringExtra(EXTRA_RELATIVE_PATH);
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) { values.put(MediaStore.Downloads.RELATIVE_PATH, "Download/SGC/" + (relativePath == null ? "Documentos" : relativePath)); values.put(MediaStore.Downloads.IS_PENDING, 1); }
            android.net.Uri uri = getContentResolver().insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, values);
            if (uri == null) throw new Exception();
            try (FileInputStream input = new FileInputStream(path); OutputStream output = getContentResolver().openOutputStream(uri)) {
                if (output == null) throw new Exception(); byte[] buffer = new byte[8192]; int count; while ((count = input.read(buffer)) > 0) output.write(buffer, 0, count);
            }
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) { ContentValues done = new ContentValues(); done.put(MediaStore.Downloads.IS_PENDING, 0); getContentResolver().update(uri, done, null, null); }
            android.widget.Toast.makeText(this, "PDF salvo na pasta SGC organizada por documento", android.widget.Toast.LENGTH_LONG).show();
        } catch (Exception error) { android.widget.Toast.makeText(this, "Não foi possível salvar o PDF.", android.widget.Toast.LENGTH_LONG).show(); }
    }
    @Override protected void onDestroy() { executor.shutdownNow(); super.onDestroy(); }
}
