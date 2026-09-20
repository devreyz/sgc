package br.rzin.sgc;

import android.os.CancellationSignal;
import android.os.Build;
import android.content.pm.PackageInfo;
import android.content.pm.PackageManager;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;
import androidx.credentials.ClearCredentialStateRequest;
import androidx.credentials.Credential;
import androidx.credentials.CredentialManager;
import androidx.credentials.CredentialManagerCallback;
import androidx.credentials.CustomCredential;
import androidx.credentials.GetCredentialRequest;
import androidx.credentials.GetCredentialResponse;
import androidx.credentials.GetPublicKeyCredentialOption;
import androidx.credentials.PublicKeyCredential;
import androidx.credentials.exceptions.GetCredentialCancellationException;
import androidx.credentials.exceptions.GetCredentialException;
import androidx.credentials.exceptions.GetCredentialProviderConfigurationException;
import androidx.credentials.exceptions.GetCredentialUnsupportedException;
import androidx.credentials.exceptions.NoCredentialException;
import androidx.credentials.exceptions.ClearCredentialException;
import androidx.credentials.exceptions.publickeycredential.GetPublicKeyCredentialDomException;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.google.android.libraries.identity.googleid.GetSignInWithGoogleOption;
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential;
import com.google.firebase.messaging.FirebaseMessaging;

@CapacitorPlugin(name = "NativeAuth")
public class NativeAuthPlugin extends Plugin {
    private PluginCall pendingCall;
    private CancellationSignal cancellationSignal;
    private PendingOperation pendingOperation;

    private enum PendingOperation {
        GOOGLE,
        PASSKEY
    }

    @PluginMethod
    public void getDiagnosticsContext(PluginCall call) {
        JSObject result = new JSObject();
        result.put("appVersion", getAppVersion());
        result.put("androidVersion", Build.VERSION.RELEASE);
        result.put("device", Build.MANUFACTURER + " " + Build.MODEL);
        call.resolve(result);
    }

    @PluginMethod
    public void getFcmToken(PluginCall call) {
        FirebaseMessaging.getInstance().getToken().addOnCompleteListener(task -> {
            if (!task.isSuccessful() || task.getResult() == null || task.getResult().isBlank()) {
                reject(call, "FCM_TOKEN_UNAVAILABLE", "Não foi possível registrar este aparelho para notificações.");
                return;
            }

            JSObject result = new JSObject();
            result.put("token", task.getResult());
            call.resolve(result);
        });
    }

    private String getAppVersion() {
        try {
            PackageInfo packageInfo = getContext()
                .getPackageManager()
                .getPackageInfo(getContext().getPackageName(), 0);
            return packageInfo.versionName == null ? "unknown" : packageInfo.versionName;
        } catch (PackageManager.NameNotFoundException exception) {
            return "unknown";
        }
    }

    @PluginMethod
    public synchronized void googleSignIn(PluginCall call) {
        if (pendingCall != null) {
            reject(call, "SIGN_IN_IN_PROGRESS", "Um login Google já está em andamento.");
            return;
        }

        String serverClientId = getContext().getString(R.string.google_web_client_id).trim();
        if (serverClientId.isEmpty()) {
            reject(call, "CONFIGURATION_ERROR", "O login Google não está configurado neste aplicativo.");
            return;
        }

        String nonce = call.getString("nonce", "").trim();
        if (nonce.isEmpty() || nonce.length() > 512) {
            reject(call, "INVALID_CHALLENGE", "Não foi possível iniciar o login com segurança.");
            return;
        }

        beginOperation(call, PendingOperation.GOOGLE);

        GetSignInWithGoogleOption googleOption = new GetSignInWithGoogleOption.Builder(serverClientId)
            .setNonce(nonce)
            .build();
        GetCredentialRequest request = new GetCredentialRequest.Builder()
            .addCredentialOption(googleOption)
            .build();

        CredentialManager.create(getContext()).getCredentialAsync(
            getActivity(),
            request,
            cancellationSignal,
            ContextCompat.getMainExecutor(getContext()),
            new CredentialManagerCallback<>() {
                @Override
                public void onResult(GetCredentialResponse result) {
                    handleCredentialResponse(result);
                }

                @Override
                public void onError(@NonNull GetCredentialException exception) {
                    handleCredentialError(exception);
                }
            }
        );
    }

    @PluginMethod
    public synchronized void passkeySignIn(PluginCall call) {
        if (pendingCall != null) {
            reject(call, "SIGN_IN_IN_PROGRESS", "Uma autenticação já está em andamento.");
            return;
        }

        String requestJson = call.getString("requestJson", "").trim();
        if (requestJson.isEmpty() || requestJson.length() > 65536) {
            reject(call, "INVALID_PASSKEY_REQUEST", "Não foi possível iniciar o acesso por biometria com segurança.");
            return;
        }

        beginOperation(call, PendingOperation.PASSKEY);

        final GetPublicKeyCredentialOption passkeyOption;
        try {
            passkeyOption = new GetPublicKeyCredentialOption(requestJson);
        } catch (RuntimeException exception) {
            takePendingCall();
            reject(call, "INVALID_PASSKEY_REQUEST", "O servidor retornou uma solicitação de biometria inválida.");
            return;
        }

        GetCredentialRequest request = new GetCredentialRequest.Builder()
            .addCredentialOption(passkeyOption)
            .build();

        CredentialManager.create(getContext()).getCredentialAsync(
            getActivity(),
            request,
            cancellationSignal,
            ContextCompat.getMainExecutor(getContext()),
            new CredentialManagerCallback<>() {
                @Override
                public void onResult(GetCredentialResponse result) {
                    handleCredentialResponse(result);
                }

                @Override
                public void onError(@NonNull GetCredentialException exception) {
                    handleCredentialError(exception);
                }
            }
        );
    }

    @PluginMethod
    public void clearCredentialState(PluginCall call) {
        CredentialManager.create(getContext()).clearCredentialStateAsync(
            new ClearCredentialStateRequest(),
            new CancellationSignal(),
            ContextCompat.getMainExecutor(getContext()),
            new CredentialManagerCallback<>() {
                @Override
                public void onResult(Void result) {
                    call.resolve();
                }

                @Override
                public void onError(@NonNull ClearCredentialException exception) {
                    reject(call, "CLEAR_CREDENTIAL_STATE_FAILED", "Não foi possível limpar o estado da conta Google.");
                }
            }
        );
    }

    private synchronized void handleCredentialResponse(GetCredentialResponse response) {
        PendingOperation operation = pendingOperation;
        PluginCall call = takePendingCall();
        if (call == null) {
            return;
        }

        Credential credential = response.getCredential();
        if (operation == PendingOperation.PASSKEY) {
            if (!(credential instanceof PublicKeyCredential publicKeyCredential)) {
                reject(call, "UNEXPECTED_CREDENTIAL", "O dispositivo retornou uma credencial não reconhecida.");
                return;
            }

            String credentialJson = publicKeyCredential.getAuthenticationResponseJson();
            if (credentialJson == null || credentialJson.isBlank()) {
                reject(call, "INVALID_PASSKEY_RESPONSE", "O dispositivo não retornou uma credencial válida.");
                return;
            }

            JSObject result = new JSObject();
            result.put("credentialJson", credentialJson);
            call.resolve(result);
            return;
        }

        if (!(credential instanceof CustomCredential customCredential)
            || !GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_CREDENTIAL.equals(customCredential.getType())) {
            reject(call, "UNEXPECTED_CREDENTIAL", "O Google retornou uma credencial não reconhecida.");
            return;
        }

        try {
            String idToken = GoogleIdTokenCredential
                .createFrom(customCredential.getData())
                .getIdToken();
            if (idToken == null || idToken.isBlank()) {
                reject(call, "MISSING_ID_TOKEN", "O Google não retornou uma identidade válida.");
                return;
            }

            JSObject result = new JSObject();
            result.put("idToken", idToken);
            call.resolve(result);
        } catch (RuntimeException exception) {
            reject(call, "INVALID_ID_TOKEN", "O Google retornou uma resposta inválida.");
        }
    }

    private synchronized void handleCredentialError(GetCredentialException exception) {
        PendingOperation operation = pendingOperation;
        PluginCall call = takePendingCall();
        if (call == null) {
            return;
        }

        if (exception instanceof GetCredentialCancellationException) {
            reject(call, "SIGN_IN_CANCELLED", "Login cancelado.");
        } else if (exception instanceof NoCredentialException) {
            if (operation == PendingOperation.PASSKEY) {
                reject(call, "NO_PASSKEY", "Nenhuma chave de acesso está disponível para o SGC neste dispositivo.");
            } else {
                reject(call, "NO_GOOGLE_ACCOUNT", "Nenhuma conta Google está disponível neste dispositivo.");
            }
        } else if (exception instanceof GetCredentialProviderConfigurationException) {
            reject(call, "PLAY_SERVICES_UNAVAILABLE", "O Google Play Services não está disponível ou precisa ser atualizado.");
        } else if (exception instanceof GetCredentialUnsupportedException) {
            reject(call, "CREDENTIAL_MANAGER_UNAVAILABLE", "O gerenciador de credenciais não está disponível.");
        } else if (operation == PendingOperation.PASSKEY
            && exception instanceof GetPublicKeyCredentialDomException domException) {
            reject(
                call,
                "PASSKEY_DOM_ERROR",
                "O Android recusou esta chave de acesso.",
                safeNativeErrorType(domException.getDomError().getType())
            );
        } else {
            if (operation == PendingOperation.PASSKEY) {
                reject(call, "PASSKEY_SIGN_IN_FAILED", "Não foi possível entrar com biometria.");
            } else {
                reject(call, "GOOGLE_SIGN_IN_FAILED", "Não foi possível entrar com o Google.");
            }
        }
    }

    private void beginOperation(PluginCall call, PendingOperation operation) {
        pendingCall = call;
        pendingOperation = operation;
        cancellationSignal = new CancellationSignal();
    }

    private PluginCall takePendingCall() {
        PluginCall call = pendingCall;
        pendingCall = null;
        pendingOperation = null;
        cancellationSignal = null;
        return call;
    }

    private void reject(PluginCall call, String code, String message) {
        reject(call, code, message, null);
    }

    private void reject(PluginCall call, String code, String message, String nativeErrorType) {
        JSObject details = new JSObject();
        details.put("code", code);
        if (nativeErrorType != null && !nativeErrorType.isBlank()) {
            details.put("nativeErrorType", nativeErrorType);
        }
        call.reject(message, code, details);
    }

    private String safeNativeErrorType(String value) {
        if (value == null) {
            return "unknown";
        }

        String sanitized = value.replaceAll("[^A-Za-z0-9_.:-]", "_");
        return sanitized.length() > 80 ? sanitized.substring(0, 80) : sanitized;
    }

    @Override
    protected synchronized void handleOnDestroy() {
        if (cancellationSignal != null) {
            cancellationSignal.cancel();
        }
        if (pendingCall != null) {
            reject(pendingCall, "SIGN_IN_CANCELLED", "Login cancelado.");
        }
        pendingCall = null;
        pendingOperation = null;
        cancellationSignal = null;
        super.handleOnDestroy();
    }
}
