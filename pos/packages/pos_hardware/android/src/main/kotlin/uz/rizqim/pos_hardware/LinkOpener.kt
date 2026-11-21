package uz.rizqim.pos_hardware

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.net.Uri
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel

/**
 * M5 S5: opens a download link in the system browser (the update notice). Only https links with a host and no
 * login part (`user:pw@`) are opened, the same rule as `isOpenableLink` on the Dart side; the intent is limited to
 * apps that are safe to open from a link (`CATEGORY_BROWSABLE`).
 *
 * Channel `uz.rizqim.pos_hardware/link`: `open` (`url` string) → `bool` (a browser took it).
 * Dart side: lib/src/link/open_link.dart.
 */
internal class LinkOpener(private val context: Context) : MethodChannel.MethodCallHandler {
    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        when (call.method) {
            "open" -> result.success(open(call.argument<String>("url")))
            else -> result.notImplemented()
        }
    }

    private fun open(url: String?): Boolean {
        if (url == null) return false
        val uri = Uri.parse(url)
        if (uri.scheme != "https" || uri.host.isNullOrEmpty() || uri.userInfo != null) return false
        return try {
            val intent = Intent(Intent.ACTION_VIEW, uri)
                .addCategory(Intent.CATEGORY_BROWSABLE)
                .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            context.applicationContext.startActivity(intent)
            true
        } catch (e: ActivityNotFoundException) {
            false
        } catch (e: RuntimeException) {
            false
        }
    }

    companion object {
        const val CHANNEL = "uz.rizqim.pos_hardware/link"
    }
}
