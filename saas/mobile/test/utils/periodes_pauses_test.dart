import 'package:flutter_test/flutter_test.dart';
import 'package:auditron_x_app/screens/admin/admin_emplois_screen.dart';

/// Une pause n'est pas une période : elle repousse la fin d'un créneau sans
/// le faire compter davantage. Mêmes règles que `App\Services\GrilleHoraire`
/// côté serveur, qui décide des périodes à rattraper.
void main() {
  setUp(() => GrillePauses.definir(const []));

  test('sans pause, la fin suit le nombre de périodes', () {
    expect(finApresPeriodes('07:30', '1'), '08:10');
    expect(finApresPeriodes('07:30', '4'), '10:10');
    expect(nombrePeriodesEntre('07:30', '10:10'), '4');
  });

  test('une entrée incomplète ne produit pas de fin', () {
    expect(finApresPeriodes('', '2'), '');
    expect(finApresPeriodes('07:30', ''), '');
    expect(finApresPeriodes('07:30', '0'), '');
  });

  test('la récréation est enjambée, sans compter pour une période', () {
    GrillePauses.definir(const [
      {'heure_debut': '09:30:00', 'heure_fin': '09:50:00'},
    ]);

    // 3 périodes s'arrêtent net au début de la récréation…
    expect(finApresPeriodes('07:30', '3'), '09:30');
    // … la 4e reprend après elle.
    expect(finApresPeriodes('07:30', '4'), '10:30');
    expect(minutesHorsPause('07:30', '10:30'), 160);
    expect(nombrePeriodesEntre('07:30', '10:30'), '4');
  });

  test('un créneau qui commence pendant une pause démarre à sa fin', () {
    GrillePauses.definir(const [
      {'heure_debut': '12:00', 'heure_fin': '13:00'},
    ]);

    expect(finApresPeriodes('12:30', '1'), '13:40');
  });

  test('une pause mal saisie est ignorée plutôt que de bloquer le calcul', () {
    GrillePauses.definir(const [
      {'heure_debut': '09:30', 'heure_fin': '09:30'},
      {'heure_debut': 'midi', 'heure_fin': '13:00'},
    ]);

    expect(finApresPeriodes('07:30', '2'), '08:50');
  });
}
