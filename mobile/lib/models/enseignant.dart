class Enseignant {
  final int id;
  final String nom;
  final String matricule;
  final String? section;

  Enseignant({required this.id, required this.nom, required this.matricule, this.section});

  factory Enseignant.fromJson(Map<String, dynamic> json) => Enseignant(
        id: json['id'] as int,
        nom: json['nom'] as String,
        matricule: json['matricule'] as String,
        section: json['section'] as String?,
      );
}
