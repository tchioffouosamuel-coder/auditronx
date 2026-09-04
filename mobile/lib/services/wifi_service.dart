import 'package:network_info_plus/network_info_plus.dart';
import 'package:permission_handler/permission_handler.dart';

/// Lecture du BSSID de la borne WiFi connectée (§4.1). Android exige la
/// permission de localisation pour accéder aux informations WiFi détaillées.
class WifiService {
  final _networkInfo = NetworkInfo();

  Future<bool> ensureLocationPermission() async {
    final status = await Permission.locationWhenInUse.request();
    return status.isGranted;
  }

  /// Retourne le BSSID de la borne connectée, ou null si indisponible
  /// (permission refusée, WiFi désactivé, ou limitation connue sur iOS — §4.1).
  Future<String?> currentBssid() async {
    final granted = await ensureLocationPermission();
    if (!granted) return null;

    return _networkInfo.getWifiBSSID();
  }
}
