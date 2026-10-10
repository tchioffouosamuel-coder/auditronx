package com.auditronx.auditron_x_app

import io.flutter.embedding.android.FlutterActivity

// Le pointage via la borne passe désormais par BLE (§hardware) — géré par le
// plugin flutter_blue_plus côté Dart, plus besoin de code natif custom ici
// (contrairement à l'ancienne connexion WiFi via WifiNetworkSpecifier).
class MainActivity : FlutterActivity()
