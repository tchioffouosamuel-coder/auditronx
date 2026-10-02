import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

extension SafeSecureStorage on FlutterSecureStorage {
  /// Comme `read`, mais une valeur illisible vaut une valeur absente.
  ///
  /// Sur Android, une valeur chiffrée avec une clé du Keystore qui n'existe
  /// plus (données restaurées par la sauvegarde automatique après une
  /// réinstallation, par exemple) fait lever une `PlatformException` à chaque
  /// lecture. Elle ne sera plus jamais déchiffrable : on la supprime pour que
  /// l'app reparte d'une valeur neuve au lieu de rester bloquée dessus.
  Future<String?> readOrNull(String key) async {
    try {
      return await read(key: key);
    } on PlatformException catch (e) {
      final details = '${e.message} ${e.details}';
      final undecryptable = details.contains('BadPaddingException') ||
          details.contains('AEADBadTagException') ||
          details.contains('IllegalBlockSizeException') ||
          details.contains('EVP_CipherFinal_ex');
      if (undecryptable) {
        try {
          await delete(key: key);
        } on PlatformException {
          // Suppression impossible : la prochaine lecture réessaiera.
        }
      }
      return null;
    }
  }
}
