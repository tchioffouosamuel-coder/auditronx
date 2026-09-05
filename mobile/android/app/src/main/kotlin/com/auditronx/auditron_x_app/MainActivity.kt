package com.auditronx.auditron_x_app

import android.content.Context
import android.net.ConnectivityManager
import android.net.Network
import android.net.NetworkCapabilities
import android.net.NetworkRequest
import android.net.wifi.WifiInfo
import android.net.wifi.WifiNetworkSpecifier
import android.os.Build
import androidx.annotation.RequiresApi
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import java.util.concurrent.atomic.AtomicBoolean

/**
 * Connexion éphémère à la borne WiFi ESP32 au moment du scan (§4.1) :
 * WifiNetworkSpecifier (API 29+) ne persiste JAMAIS le réseau dans la liste
 * WiFi enregistrée du téléphone — contrairement à `WifiManager.addNetwork`
 * (déprécié) — et libère la station côté borne dès `unregisterNetworkCallback`,
 * ce qui évite de saturer les 4 emplacements de l'AP softAP de l'ESP32 avec
 * des téléphones qui restent connectés en arrière-plan après leur scan.
 *
 * En dessous d'Android 10, WifiNetworkSpecifier n'existe pas et Android
 * n'autorise plus les apps tierces à gérer les réseaux enregistrés : on
 * renvoie UNSUPPORTED_SDK et l'app Flutter retombe sur l'ancien comportement
 * (BSSID du réseau déjà connecté manuellement par l'utilisateur).
 */
class MainActivity : FlutterActivity() {
    private val channelName = "auditron/wifi"
    private var activeCallback: ConnectivityManager.NetworkCallback? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, channelName).setMethodCallHandler { call, result ->
            when (call.method) {
                "connect" -> {
                    val ssid = call.argument<String>("ssid")
                    val password = call.argument<String>("password")
                    if (ssid.isNullOrBlank()) {
                        result.error("INVALID_ARGS", "ssid manquant", null)
                        return@setMethodCallHandler
                    }
                    if (Build.VERSION.SDK_INT < Build.VERSION_CODES.Q) {
                        result.error("UNSUPPORTED_SDK", "Connexion auto indisponible avant Android 10", null)
                        return@setMethodCallHandler
                    }
                    connect(ssid, password, result)
                }

                "release" -> {
                    release()
                    result.success(null)
                }

                else -> result.notImplemented()
            }
        }
    }

    @RequiresApi(Build.VERSION_CODES.Q)
    private fun connect(ssid: String, password: String?, result: MethodChannel.Result) {
        release() // une seule connexion borne active à la fois

        val connectivityManager = getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager

        val specifierBuilder = WifiNetworkSpecifier.Builder().setSsid(ssid)
        if (!password.isNullOrEmpty()) {
            specifierBuilder.setWpa2Passphrase(password)
        }

        val request = NetworkRequest.Builder()
            .addTransportType(NetworkCapabilities.TRANSPORT_WIFI)
            .removeCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
            .setNetworkSpecifier(specifierBuilder.build())
            .build()

        val settled = AtomicBoolean(false)
        val timeoutMs = 15_000L
        val mainHandler = android.os.Handler(android.os.Looper.getMainLooper())

        val callback = object : ConnectivityManager.NetworkCallback() {
            override fun onAvailable(network: Network) {
                if (!settled.compareAndSet(false, true)) return
                val bssid = bssidOf(connectivityManager, network)
                mainHandler.post { result.success(bssid) }
            }

            override fun onUnavailable() {
                if (!settled.compareAndSet(false, true)) return
                mainHandler.post { result.error("CONNECT_FAILED", "Impossible de rejoindre $ssid", null) }
            }
        }

        activeCallback = callback
        connectivityManager.requestNetwork(request, callback, mainHandler, timeoutMs.toInt())
    }

    @RequiresApi(Build.VERSION_CODES.Q)
    private fun bssidOf(connectivityManager: ConnectivityManager, network: Network): String? {
        val capabilities = connectivityManager.getNetworkCapabilities(network) ?: return null
        val transportInfo = capabilities.transportInfo
        return (transportInfo as? WifiInfo)?.bssid
    }

    private fun release() {
        val callback = activeCallback ?: return
        activeCallback = null
        try {
            val connectivityManager = getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
            connectivityManager.unregisterNetworkCallback(callback)
        } catch (_: IllegalArgumentException) {
            // déjà libéré (timeout écoulé côté système) — rien à faire.
        }
    }

    override fun onDestroy() {
        release()
        super.onDestroy()
    }
}
