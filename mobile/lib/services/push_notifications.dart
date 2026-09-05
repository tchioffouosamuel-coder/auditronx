import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'api_client.dart';

/// Reçu par Firebase pour les messages arrivant app fermée/en arrière-plan.
/// Doit être une fonction top-level (pas une méthode) et annotée
/// `vm:entry-point` : Firebase la relance dans un isolate séparé.
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  // Rien à faire ici : le payload `notification` est déjà affiché par l'OS
  // (Android/iOS) sans code applicatif tant que l'app n'est pas au premier
  // plan. On ne fait qu'initialiser Firebase pour que l'isolate soit valide.
}

/// Enregistrement du device auprès de Firebase Cloud Messaging et
/// synchronisation du token avec l'API (`POST /devices/fcm-token`), pour que
/// le backend puisse pousser les notifications enseignant (§ TeacherNotification)
/// vers ce téléphone précis.
class PushNotifications {
  PushNotifications._();
  static final PushNotifications instance = PushNotifications._();

  /// À appeler une fois le device activé (token Sanctum disponible) — au
  /// démarrage si déjà activé, ou juste après activation/OTP.
  Future<void> registerDevice() async {
    try {
      final messaging = FirebaseMessaging.instance;

      await messaging.requestPermission(alert: true, badge: true, sound: true);

      final fcmToken = await messaging.getToken();
      if (fcmToken != null) {
        await _sendTokenToApi(fcmToken);
      }

      // Le token FCM tourne (réinstall, changement d'app id, etc.) : on le
      // resynchronise à chaque rotation, pas seulement au premier lancement.
      messaging.onTokenRefresh.listen(_sendTokenToApi);
    } catch (e) {
      // Permission refusée, pas de Google Play Services (émulateur sans GMS),
      // etc. : le pointage reste fonctionnel sans push, donc on avale l'erreur.
      debugPrint('PushNotifications.registerDevice: $e');
    }
  }

  Future<void> _sendTokenToApi(String fcmToken) async {
    try {
      await ApiClient.instance.post('/devices/fcm-token', {'fcm_token': fcmToken});
    } catch (e) {
      debugPrint('PushNotifications._sendTokenToApi: $e');
    }
  }
}
