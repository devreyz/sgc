package br.rzin.sgc;

import android.app.Activity;
import android.content.Intent;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.net.Uri;
import android.provider.MediaStore;
import android.util.Base64;

import androidx.core.content.FileProvider;
import androidx.exifinterface.media.ExifInterface;
import androidx.activity.result.ActivityResult;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.ActivityCallback;
import com.getcapacitor.annotation.CapacitorPlugin;

import java.io.ByteArrayOutputStream;
import java.io.File;
import java.io.IOException;
import java.util.UUID;

@CapacitorPlugin(name = "NativeCamera")
public class NativeCameraPlugin extends Plugin {
    private static final int MAX_DIMENSION = 2048;
    private static final int MAX_ENCODED_BYTES = 12 * 1024 * 1024;
    private File pendingPhoto;

    @PluginMethod
    public void takePhoto(PluginCall call) {
        Intent intent = new Intent(MediaStore.ACTION_IMAGE_CAPTURE);
        if (intent.resolveActivity(getContext().getPackageManager()) == null) {
            call.reject("Nenhum aplicativo de câmera está disponível.", "CAMERA_UNAVAILABLE");
            return;
        }

        try {
            File directory = new File(getContext().getCacheDir(), "camera-evidence");
            if (!directory.exists() && !directory.mkdirs()) {
                throw new IOException("Não foi possível preparar o cache da câmera.");
            }
            pendingPhoto = new File(directory, "evidencia-" + UUID.randomUUID() + ".jpg");
            Uri uri = FileProvider.getUriForFile(
                getContext(),
                getContext().getPackageName() + ".fileprovider",
                pendingPhoto
            );
            intent.putExtra(MediaStore.EXTRA_OUTPUT, uri);
            intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION | Intent.FLAG_GRANT_WRITE_URI_PERMISSION);
            startActivityForResult(call, intent, "cameraResult");
        } catch (IOException | RuntimeException exception) {
            clearPendingPhoto();
            call.reject("Não foi possível abrir a câmera com segurança.", "CAMERA_START_FAILED");
        }
    }

    @ActivityCallback
    private void cameraResult(PluginCall call, ActivityResult result) {
        if (call == null) {
            clearPendingPhoto();
            return;
        }
        if (result.getResultCode() != Activity.RESULT_OK) {
            clearPendingPhoto();
            call.reject("Captura cancelada.", "CAMERA_CANCELLED");
            return;
        }
        if (pendingPhoto == null || !pendingPhoto.isFile() || pendingPhoto.length() <= 0) {
            clearPendingPhoto();
            call.reject("A câmera não devolveu uma imagem válida.", "CAMERA_EMPTY_RESULT");
            return;
        }

        try {
            BitmapFactory.Options bounds = new BitmapFactory.Options();
            bounds.inJustDecodeBounds = true;
            BitmapFactory.decodeFile(pendingPhoto.getAbsolutePath(), bounds);
            if (bounds.outWidth <= 0 || bounds.outHeight <= 0) throw new IOException("Imagem inválida.");

            int sample = 1;
            while (Math.max(bounds.outWidth / sample, bounds.outHeight / sample) > MAX_DIMENSION * 2) sample *= 2;
            BitmapFactory.Options options = new BitmapFactory.Options();
            options.inSampleSize = sample;
            Bitmap bitmap = BitmapFactory.decodeFile(pendingPhoto.getAbsolutePath(), options);
            if (bitmap == null) throw new IOException("Imagem inválida.");
            bitmap = rotateIfNeeded(bitmap, pendingPhoto);
            bitmap = scale(bitmap);

            ByteArrayOutputStream output = new ByteArrayOutputStream();
            if (!bitmap.compress(Bitmap.CompressFormat.JPEG, 86, output)) throw new IOException("Falha ao comprimir imagem.");
            bitmap.recycle();
            byte[] bytes = output.toByteArray();
            if (bytes.length <= 0 || bytes.length > MAX_ENCODED_BYTES) throw new IOException("Imagem excede o limite permitido.");

            JSObject value = new JSObject();
            value.put("dataUrl", "data:image/jpeg;base64," + Base64.encodeToString(bytes, Base64.NO_WRAP));
            value.put("mimeType", "image/jpeg");
            value.put("fileName", "evidencia-" + System.currentTimeMillis() + ".jpg");
            clearPendingPhoto();
            call.resolve(value);
        } catch (IOException | OutOfMemoryError exception) {
            clearPendingPhoto();
            call.reject("Não foi possível processar a foto capturada.", "CAMERA_PROCESS_FAILED");
        }
    }

    private Bitmap scale(Bitmap source) {
        int width = source.getWidth();
        int height = source.getHeight();
        float ratio = Math.min(1f, (float) MAX_DIMENSION / Math.max(width, height));
        if (ratio >= 1f) return source;
        Bitmap scaled = Bitmap.createScaledBitmap(source, Math.max(1, Math.round(width * ratio)), Math.max(1, Math.round(height * ratio)), true);
        if (scaled != source) source.recycle();
        return scaled;
    }

    private Bitmap rotateIfNeeded(Bitmap source, File file) throws IOException {
        int orientation = new ExifInterface(file).getAttributeInt(ExifInterface.TAG_ORIENTATION, ExifInterface.ORIENTATION_NORMAL);
        float degrees = switch (orientation) {
            case ExifInterface.ORIENTATION_ROTATE_90 -> 90f;
            case ExifInterface.ORIENTATION_ROTATE_180 -> 180f;
            case ExifInterface.ORIENTATION_ROTATE_270 -> 270f;
            default -> 0f;
        };
        if (degrees == 0f) return source;
        android.graphics.Matrix matrix = new android.graphics.Matrix();
        matrix.postRotate(degrees);
        Bitmap rotated = Bitmap.createBitmap(source, 0, 0, source.getWidth(), source.getHeight(), matrix, true);
        if (rotated != source) source.recycle();
        return rotated;
    }

    private void clearPendingPhoto() {
        if (pendingPhoto != null && pendingPhoto.exists()) pendingPhoto.delete();
        pendingPhoto = null;
    }
}
