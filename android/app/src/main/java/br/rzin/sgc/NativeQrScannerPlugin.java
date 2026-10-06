package br.rzin.sgc;

import android.app.Activity;
import android.content.Intent;

import androidx.activity.result.ActivityResult;

import com.getcapacitor.JSArray;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.ActivityCallback;
import com.getcapacitor.annotation.CapacitorPlugin;

import java.util.ArrayList;

@CapacitorPlugin(name = "NativeQrScanner")
public class NativeQrScannerPlugin extends Plugin {
    @PluginMethod
    public void scan(PluginCall call) {
        Intent intent = new Intent(getContext(), NativeQrScannerActivity.class);
        intent.putExtra(NativeQrScannerActivity.EXTRA_BATCH, call.getBoolean("batch", false));
        intent.putExtra(NativeQrScannerActivity.EXTRA_VERIFICATION_URL, call.getString("verificationUrl", ""));
        intent.putExtra(NativeQrScannerActivity.EXTRA_CSRF_TOKEN, call.getString("csrfToken", ""));
        JSObject selectionPayload = call.getObject("selectionPayload");
        intent.putExtra(NativeQrScannerActivity.EXTRA_SELECTION_PAYLOAD, selectionPayload == null ? "" : selectionPayload.toString());
        startActivityForResult(call, intent, "scannerResult");
    }

    @ActivityCallback
    private void scannerResult(PluginCall call, ActivityResult result) {
        if (call == null) return;
        if (result.getResultCode() != Activity.RESULT_OK || result.getData() == null) {
            call.reject("Leitura cancelada.", "SCAN_CANCELLED");
            return;
        }
        ArrayList<String> codes = result.getData().getStringArrayListExtra(NativeQrScannerActivity.RESULT_CODES);
        JSArray values = new JSArray();
        if (codes != null) for (String code : codes) values.put(code);
        JSObject response = new JSObject();
        response.put("codes", values);
        response.put("code", codes == null || codes.isEmpty() ? "" : codes.get(0));
        call.resolve(response);
    }
}
