// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'items.g.dart';

@JsonSerializable()
class Items {
  const Items({
    required this.id,
    required this.title,
    required this.message,
    required this.type,
    required this.status,
    required this.isRead,
    required this.readAt,
    required this.sentAt,
  });

  factory Items.fromJson(Map<String, Object?> json) => _$ItemsFromJson(json);

  final int id;
  final String title;
  final String message;
  final String type;
  final String status;
  @JsonKey(name: 'is_read')
  final bool isRead;
  @JsonKey(name: 'read_at')
  final String? readAt;
  @JsonKey(name: 'sent_at')
  final String sentAt;

  Map<String, Object?> toJson() => _$ItemsToJson(this);
}
