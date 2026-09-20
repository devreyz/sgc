package br.rzin.sgc;

import android.content.ContentValues;
import android.content.Intent;
import android.os.Build;
import android.provider.MediaStore;
import android.util.Base64;

import androidx.core.content.FileProvider;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.OutputStream;

@CapacitorPlugin(name = "NativeDocument")
public class NativeDocumentPlugin extends Plugin {
    private static final int MAX_DOCUMENT_BYTES = 35 * 1024 * 1024;

    @PluginMethod
    public void openPdf(PluginCall call) {
        byte[] bytes = decode(call);
        if (bytes == null) return;

        try {
            File directory = new File(getContext().getCacheDir(), "pdf-documents");
            if (!directory.exists() && !directory.mkdirs()) throw new IOException("Não foi possível criar o cache.");
            File pdf = new File(directory, safeFileName(call.getString("fileName", "documento-sgc.pdf")));
            try (FileOutputStream output = new FileOutputStream(pdf, false)) { output.write(bytes); }

            Intent intent = new Intent(getContext(), PdfViewerActivity.class);
            intent.putExtra(PdfViewerActivity.EXTRA_FILE_PATH, pdf.getAbsolutePath());
            intent.putExtra(PdfViewerActivity.EXTRA_TITLE, call.getString("title", "Documento SGC"));
            intent.putExtra(PdfViewerActivity.EXTRA_RELATIVE_PATH, safeRelativePath(call.getString("relativePath", "Documentos")));
            intent.putExtra(PdfViewerActivity.EXTRA_ORIGIN, call.getString("origin", ""));
            intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            getContext().startActivity(intent);
            call.resolve();
        } catch (IOException exception) {
            call.reject("Não foi possível preparar o PDF para visualização.", "PDF_CACHE_FAILED");
        } catch (RuntimeException exception) {
            call.reject("Não foi possível abrir este PDF no aplicativo.", "PDF_OPEN_FAILED");
        }
    }

    @PluginMethod
    public void downloadPdf(PluginCall call) {
        byte[] bytes = decode(call);
        if (bytes == null) return;
        String name = safeFileName(call.getString("fileName", "documento-sgc.pdf"));

        try {
            ContentValues values = new ContentValues();
            values.put(MediaStore.Downloads.DISPLAY_NAME, name);
            values.put(MediaStore.Downloads.MIME_TYPE, "application/pdf");
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                values.put(MediaStore.Downloads.RELATIVE_PATH, "Download/SGC/" + safeRelativePath(call.getString("relativePath", "Documentos")));
                values.put(MediaStore.Downloads.IS_PENDING, 1);
            }
            android.net.Uri uri = getContext().getContentResolver().insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, values);
            if (uri == null) throw new IOException("Não foi possível reservar o arquivo.");
            try (OutputStream output = getContext().getContentResolver().openOutputStream(uri)) {
                if (output == null) throw new IOException("Não foi possível abrir o arquivo.");
                output.write(bytes);
            }
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                ContentValues done = new ContentValues(); done.put(MediaStore.Downloads.IS_PENDING, 0);
                getContext().getContentResolver().update(uri, done, null, null);
            }
            JSObject result = new JSObject(); result.put("saved", true); result.put("uri", uri.toString()); call.resolve(result);
        } catch (IOException | SecurityException exception) {
            call.reject("Não foi possível salvar o PDF em Downloads.", "PDF_DOWNLOAD_FAILED");
        }
    }

    @PluginMethod
    public void sharePdf(PluginCall call) {
        byte[] bytes = decode(call);
        if (bytes == null) return;

        try {
            File directory = new File(getContext().getCacheDir(), "pdf-documents");
            if (!directory.exists() && !directory.mkdirs()) throw new IOException("Não foi possível criar o cache.");
            File pdf = new File(directory, safeFileName(call.getString("fileName", "documento-sgc.pdf")));
            try (FileOutputStream output = new FileOutputStream(pdf, false)) { output.write(bytes); }

            android.net.Uri uri = FileProvider.getUriForFile(getContext(), getContext().getPackageName() + ".fileprovider", pdf);
            Intent share = new Intent(Intent.ACTION_SEND);
            share.setType("application/pdf");
            share.putExtra(Intent.EXTRA_STREAM, uri);
            share.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
            getContext().startActivity(Intent.createChooser(share, "Compartilhar PDF").addFlags(Intent.FLAG_ACTIVITY_NEW_TASK));
            call.resolve();
        } catch (IOException | SecurityException exception) {
            call.reject("Não foi possível compartilhar o PDF.", "PDF_SHARE_FAILED");
        }
    }

    private byte[] decode(PluginCall call) {
        try {
            String value = call.getString("base64", "");
            int comma = value.indexOf(',');
            if (value.startsWith("data:") && comma >= 0) value = value.substring(comma + 1);
            byte[] bytes = Base64.decode(value, Base64.DEFAULT);
            if (bytes.length == 0 || bytes.length > MAX_DOCUMENT_BYTES) {
                call.reject("O PDF está vazio ou excede o limite de 35 MB.", "INVALID_PDF_SIZE"); return null;
            }
            return bytes;
        } catch (IllegalArgumentException exception) {
            call.reject("O PDF gerado é inválido.", "INVALID_PDF_DATA"); return null;
        }
    }

    private String safeFileName(String value) {
        String name = value.replaceAll("[^A-Za-z0-9._-]", "-");
        if (name.isBlank()) name = "documento-sgc.pdf";
        return name.toLowerCase().endsWith(".pdf") ? name : name + ".pdf";
    }

    private String safeRelativePath(String value) {
        String[] segments = value.replace('\\', '/').split("/");
        StringBuilder safe = new StringBuilder();
        for (String segment : segments) {
            String cleaned = segment.replaceAll("[^A-Za-z0-9._-]", "-").replaceAll("-+", "-");
            if (cleaned.isBlank() || cleaned.equals(".") || cleaned.equals("..")) continue;
            if (safe.length() > 0) safe.append('/');
            safe.append(cleaned);
        }
        return safe.length() == 0 ? "Documentos" : safe.toString();
    }
}
