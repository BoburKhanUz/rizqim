// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'invoice.dart';

part 'notice.g.dart';

@JsonSerializable()
class Notice {
  const Notice({
    required this.level,
    required this.message,
    required this.daysOverdue,
    required this.lockAt,
    required this.invoice,
  });

  factory Notice.fromJson(Map<String, Object?> json) => _$NoticeFromJson(json);

  final String level;
  final String message;
  @JsonKey(name: 'days_overdue')
  final int daysOverdue;
  @JsonKey(name: 'lock_at')
  final String? lockAt;
  final Invoice invoice;

  Map<String, Object?> toJson() => _$NoticeToJson(this);
}
