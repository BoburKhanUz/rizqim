package uz.rizqim.pos_hardware

import android.app.Activity
import android.content.Context
import android.content.pm.PackageManager
import io.flutter.plugin.common.PluginRegistry

/**
 * Runtime permissions for the transports (Bluetooth on Android 12+). The plugin forwards the activity's
 * onRequestPermissionsResult here; without an activity (a background engine) a request answers `false`.
 */
internal class Permissions(private val context: Context) : PluginRegistry.RequestPermissionsResultListener {
    var activity: Activity? = null

    private val pending = HashMap<Int, (Boolean) -> Unit>()
    private var nextCode = REQUEST_BASE

    fun has(permissions: Array<String>): Boolean =
        permissions.all { context.checkSelfPermission(it) == PackageManager.PERMISSION_GRANTED }

    /** Asks for [permissions] (all needed); [done] runs on the main thread with the outcome. */
    fun request(permissions: Array<String>, done: (Boolean) -> Unit) {
        if (has(permissions)) {
            done(true)
            return
        }
        val a = activity
        if (a == null) {
            done(false)
            return
        }
        val code = nextCode++
        if (nextCode > REQUEST_BASE + 0xFF) nextCode = REQUEST_BASE
        pending[code] = done
        a.requestPermissions(permissions, code)
    }

    override fun onRequestPermissionsResult(
        requestCode: Int,
        permissions: Array<out String>,
        grantResults: IntArray,
    ): Boolean {
        val done = pending.remove(requestCode) ?: return false
        done(grantResults.isNotEmpty() && grantResults.all { it == PackageManager.PERMISSION_GRANTED })
        return true
    }

    /** The activity went away: nobody will answer the open requests. */
    fun cancelAll() {
        val waiting = pending.values.toList()
        pending.clear()
        waiting.forEach { it(false) }
    }

    companion object {
        private const val REQUEST_BASE = 0x5200
    }
}
