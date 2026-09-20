package br.rzin.sgc;

import android.content.Intent;
import android.net.Uri;
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

@CapacitorPlugin(name = "NativeShare")
public class NativeSharePlugin extends Plugin {
    private static final int MAX_IMAGE_BYTES = 20 * 1024 * 1024;

    @PluginMethod
    public void shareImage(PluginCall call) {
        String encodedImage = call.getString("base64", "");
        String fileName = safeFileName(call.getString("fileName", "sgc-imagem.png"));

        try {
            byte[] imageBytes = Base64.decode(stripDataUrlPrefix(encodedImage), Base64.DEFAULT);
            if (imageBytes.length == 0 || imageBytes.length > MAX_IMAGE_BYTES) {
                call.reject("A imagem está vazia ou excede o limite permitido.", "INVALID_IMAGE_SIZE");
                return;
            }

            File directory = new File(getContext().getCacheDir(), "shared-images");
            if (!directory.exists() && !directory.mkdirs()) {
                call.reject("Não foi possível preparar a imagem para compartilhamento.", "SHARE_CACHE_FAILED");
                return;
            }

            File image = new File(directory, fileName);
            try (FileOutputStream output = new FileOutputStream(image, false)) {
                output.write(imageBytes);
            }

            Uri uri = FileProvider.getUriForFile(
                getContext(),
                getContext().getPackageName() + ".fileprovider",
                image
            );

            Intent shareIntent = new Intent(Intent.ACTION_SEND);
            shareIntent.setType("image/png");
            shareIntent.putExtra(Intent.EXTRA_STREAM, uri);
            shareIntent.putExtra(Intent.EXTRA_SUBJECT, call.getString("title", "Imagem do SGC"));
            shareIntent.putExtra(Intent.EXTRA_TEXT, call.getString("text", ""));
            shareIntent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);

            Intent chooser = Intent.createChooser(shareIntent, "Compartilhar imagem");
            chooser.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            getContext().startActivity(chooser);

            JSObject result = new JSObject();
            result.put("shared", true);
            call.resolve(result);
        } catch (IllegalArgumentException exception) {
            call.reject("A imagem gerada é inválida.", "INVALID_IMAGE_DATA");
        } catch (IOException exception) {
            call.reject("Não foi possível salvar a imagem temporária.", "SHARE_FILE_FAILED");
        } catch (RuntimeException exception) {
            call.reject("Nenhum aplicativo conseguiu abrir o compartilhamento.", "SHARE_INTENT_FAILED");
        }
    }

    private String stripDataUrlPrefix(String value) {
        int separator = value.indexOf(',');
        return value.startsWith("data:") && separator >= 0
            ? value.substring(separator + 1)
            : value;
    }

    private String safeFileName(String value) {
        String normalized = value.replaceAll("[^A-Za-z0-9._-]", "-");
        if (normalized.isBlank()) {
            return "sgc-imagem.png";
        }

        return normalized.endsWith(".png") ? normalized : normalized + ".png";
    }
}
