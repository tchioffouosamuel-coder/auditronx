package com.auditronx.auditron_x_app

import android.content.Context
import android.net.ConnectivityManager
import android.net.Network
import android.net.NetworkCapabilities
import android.net.NetworkRequest
import android.net.wifi.WifiNetworkSpecifier
import android.os.Build
import android.os.Handler
import android.os.Looper
import androidx.annotation.RequiresApi
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import org.json.JSONObject
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.atomic.AtomicBoolean

/**
 * Pointage via la borne WiFi ESP32 (§4.1, §hardware) : le téléphone n'appelle
 * JAMAIS l'API distante directement pour un scan — il parle en HTTP local à
 * la borne (`POST http://192.168.4.1/scan`), qui met le paquet en file sur sa
 * carte SD et le pousse elle-même vers l'API à son rythme. Ça permet au
 * téléphone de scanner sans connexion internet propre : seule l'activation
 * initiale de l'app en a besoin.
 *
 * La connexion WiFi passe par `WifiNetworkSpecifier` (API 29+) : elle n'est
 * jamais ajoutée à la liste WiFi enregistrée du téléphone, et n'est PAS la
 * route réseau par défaut du process — un simple appel HTTP Dart ne
 * l'emprunterait pas puisque ce réseau n'a pas d'internet. L'appel HTTP vers
 * la borne doit donc se faire ici, sur le `Network` spécifique renvoyé par
 * `onAvailable`, via `Network.openConnection()` — impossible à faire
 * autrement que côté natif.
 *
 * En dessous d'Android 10, WifiNetworkSpecifier n'existe pas : on renvoie
 * UNSUPPORTED_SDK et l'app Flutter retombe sur un POST HTTP classique en
 * supposant que l'utilisateur est déjà connecté manuellement au WiFi de la
 * borne (qui devient alors la route par défaut du téléphone).
 */
class MainActivity : FlutterActivity() {
    private val channelName = "auditron/wifi"
    private var activeCallback: ConnectivityManager.NetworkCallback? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, channelName).setMethodCallHandler { call, result ->
            when (call.method) {
                "scanViaBorne" -> {
                    val ssid = call.argument<String>("ssid")
                    if (ssid.isNullOrBlank()) {
                        result.error("INVALID_ARGS", "ssid manquant", null)
                        return@setMethodCallHandler
                    }
                    if (Build.VERSION.SDK_INT < Build.VERSION_CODES.Q) {
                        result.error("UNSUPPORTED_SDK", "Connexion auto indisponible avant Android 10", null)
                        return@setMethodCallHandler
                    }
                    scanViaBorne(ssid, call.argument<String>("password"), call, result)
                }

                "isWifiEnabled" -> {
                    val wifiManager = applicationContext.getSystemService(Context.WIFI_SERVICE) as android.net.wifi.WifiManager
                    result.success(wifiManager.isWifiEnabled)
                }

                "openWifiPanel" -> {
                    // Panneau rapide système (bottom sheet) pour activer le WiFi en un
                    // tap, sans quitter l'app — depuis Android 10, aucune app tierce ne
                    // peut activer le WiFi par code (WifiManager.setWifiEnabled est un
                    // no-op), seul l'utilisateur peut le faire.
                    try {
                        startActivity(android.content.Intent(android.provider.Settings.Panel.ACTION_WIFI))
                        result.success(null)
                    } catch (e: Exception) {
                        result.error("PANEL_FAILED", e.message, null)
                    }
                }

                else -> result.notImplemented()
            }
        }
    }

    @RequiresApi(Build.VERSION_CODES.Q)
    private fun scanViaBorne(ssid: String, password: String?, call: MethodCall, result: MethodChannel.Result) {
        releaseBorneNetwork() // une seule connexion borne active à la fois

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
        val timeoutMs = 15_000
        val mainHandler = Handler(Looper.getMainLooper())

        val callback = object : ConnectivityManager.NetworkCallback() {
            override fun onAvailable(network: Network) {
                if (!settled.compareAndSet(false, true)) return
                // I/O réseau : jamais sur le thread principal.
                Thread {
                    val bssid = bssidOf(connectivityManager, network)
                    try {
                        val (statusCode, body) = postOverNetwork(network, buildScanBody(call, bssid))
                        mainHandler.post { result.success(mapOf("statusCode" to statusCode, "body" to body)) }
                    } catch (e: Exception) {
                        mainHandler.post { result.error("HTTP_FAILED", e.message, null) }
                    } finally {
                        releaseBorneNetwork()
                    }
                }.start()
            }

            override fun onUnavailable() {
                if (!settled.compareAndSet(false, true)) return
                mainHandler.post { result.error("CONNECT_FAILED", "Impossible de rejoindre $ssid", null) }
            }
        }

        activeCallback = callback
        connectivityManager.requestNetwork(request, callback, mainHandler, timeoutMs)
    }

    /** Construit le corps JSON attendu par `POST /scan` (voir hardware/README.md). */
    private fun buildScanBody(call: MethodCall, bssid: String?): String {
        val payload = JSONObject()
        payload.put("qr_code", call.argument<String>("qrCode"))
        payload.put("bssid", bssid ?: "")
        call.argument<Int>("enseignantId")?.let { payload.put("enseignant_id", it) }
        call.argument<String>("motif")?.let { payload.put("motif", it) }

        val body = JSONObject()
        body.put("type", call.argument<String>("type"))
        body.put("teacher_token", call.argument<String>("teacherToken"))
        body.put("payload", payload)
        return body.toString()
    }

    @RequiresApi(Build.VERSION_CODES.Q)
    private fun bssidOf(connectivityManager: ConnectivityManager, network: Network): String? {
        val capabilities = connectivityManager.getNetworkCapabilities(network) ?: return null
        val transportInfo = capabilities.transportInfo
        return (transportInfo as? android.net.wifi.WifiInfo)?.bssid
    }

    /** POST JSON sur le `Network` donné — seul moyen d'atteindre la borne via une
     * connexion WifiNetworkSpecifier, qui n'est pas la route par défaut du process. */
    private fun postOverNetwork(network: Network, jsonBody: String): Pair<Int, String> {
        val url = URL("http://$BORNE_IP/scan")
        val connection = network.openConnection(url) as HttpURLConnection
        connection.requestMethod = "POST"
        connection.doOutput = true
        connection.connectTimeout = 8_000
        connection.readTimeout = 8_000
        connection.setRequestProperty("Content-Type", "application/json")

        try {
            OutputStreamWriter(connection.outputStream, Charsets.UTF_8).use { it.write(jsonBody) }
            val status = connection.responseCode
            val stream = if (status in 200..299) connection.inputStream else connection.errorStream
            val text = stream?.bufferedReader(Charsets.UTF_8)?.use { it.readText() } ?: ""
            return status to text
        } finally {
            connection.disconnect()
        }
    }

    private fun releaseBorneNetwork() {
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
        releaseBorneNetwork()
        super.onDestroy()
    }

    companion object {
        // Adresse par défaut du point d'accès WiFi ESP32 (WiFi.softAP() sans
        // configuration IP custom, voir esp32_borne/src/main.cpp).
        private const val BORNE_IP = "192.168.4.1"
    }
}
