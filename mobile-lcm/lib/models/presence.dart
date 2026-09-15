class PresenceEntry {
  final int id;
  final String date;
  final String? heureArrivee;
  final String? heureDepart;
  final String source;
  final int? minutesRetard;

  PresenceEntry({
    required this.id,
    required this.date,
    required this.source,
    this.heureArrivee,
    this.heureDepart,
    this.minutesRetard,
  });

  bool get enRetard => (minutesRetard ?? 0) > 0;

  factory PresenceEntry.fromJson(Map<String, dynamic> json) => PresenceEntry(
        id: json['id'] as int,
        date: json['date'] as String,
        heureArrivee: json['heure_arrivee'] as String?,
        heureDepart: json['heure_depart'] as String?,
        source: json['source'] as String,
        minutesRetard: json['minutes_retard'] as int?,
      );
}
