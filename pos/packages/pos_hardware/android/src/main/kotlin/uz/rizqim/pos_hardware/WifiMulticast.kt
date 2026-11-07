package uz.rizqim.pos_hardware

import android.content.Context
import android.net.wifi.WifiManager
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel

/**
 * P6 local bridge: `WifiManager.MulticastLock` while the waiter, KDS or customer display app listens for the till's
 * UDP beacon (pos_lan `LanDiscovery`). Many phones drop broadcast datagrams on Wi-Fi to save power unless an app holds
 * this lock, so it is held only for the few seconds of a discovery and released right after.
 *
 * Channel `uz.rizqim.pos_hardware/multicast`: `acquire` → `bool` (held), `release` → `null`, `held` → `bool`.
 * Needs `CHANGE_WIFI_MULTICAST_STATE` (a normal permission, granted at install; declared in this plugin's manifest).
 * Dart side: lib/src/network/wifi_multicast_lock.dart.
 */
internal class WifiMulticast(private val context: Context) : MethodChannel.MethodCallHandler {
    private var lock: WifiManager.MulticastLock? = null

    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        when (call.method) {
            "acquire" -> result.success(acquire())
            "release" -> {
                release()
                result.success(null)
            }
            "held" -> result.success(lock?.isHeld == true)
            else -> result.notImplemented()
        }
    }

    private fun acquire(): Boolean {
        val current = lock
        if (current != null && current.isHeld) return true
        val wifi = context.applicationContext.getSystemService(Context.WIFI_SERVICE) as? WifiManager
            ?: return false
        return try {
            val next = wifi.createMulticastLock(TAG)
            // One acquire, one release: a second acquire must not need two releases
            next.setReferenceCounted(false)
            next.acquire()
            lock = next
            next.isHeld
        } catch (e: SecurityException) {
            false
        } catch (e: RuntimeException) {
            false
        }
    }

    private fun release() {
        val current = lock ?: return
        lock = null
        try {
            if (current.isHeld) current.release()
        } catch (e: RuntimeException) {
            // Already released by the system
        }
    }

    fun dispose() = release()

    companion object {
        const val CHANNEL = "uz.rizqim.pos_hardware/multicast"
        private const val TAG = "rizqim-lan"
    }
}
