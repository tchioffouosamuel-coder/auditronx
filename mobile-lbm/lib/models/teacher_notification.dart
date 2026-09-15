class TeacherNotificationEntry {
  final int id;
  final String type;
  final String message;
  final String? readAt;
  final String createdAt;

  TeacherNotificationEntry({
    required this.id,
    required this.type,
    required this.message,
    required this.createdAt,
    this.readAt,
  });

  bool get isRead => readAt != null;

  factory TeacherNotificationEntry.fromJson(Map<String, dynamic> json) => TeacherNotificationEntry(
        id: json['id'] as int,
        type: json['type'] as String,
        message: json['message'] as String,
        readAt: json['read_at'] as String?,
        createdAt: json['created_at'] as String,
      );
}
