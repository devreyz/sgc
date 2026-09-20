package br.rzin.sgc;

import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

/** Bridge for the immediate native transition shown while a web route loads. */
@CapacitorPlugin(name = "NativeNavigation")
public class NativeNavigationPlugin extends Plugin {
    @PluginMethod
    public void show(PluginCall call) {
        if (getActivity() instanceof MainActivity activity) {
            activity.showNavigationLoading(call.getString("message", "Abrindo…"));
        }
        call.resolve();
    }

    @PluginMethod
    public void hide(PluginCall call) {
        if (getActivity() instanceof MainActivity activity) {
            activity.hideNavigationLoading();
        }
        call.resolve();
    }
}
