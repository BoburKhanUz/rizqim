// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'status_history.g.dart';

@JsonSerializable()
class StatusHistory {
  const StatusHistory({
    required this.fromStatus,
    required this.status,
    required this.createdAt,
  });

  factory StatusHistory.fromJson(Map<String, Object?> json) =>
      _$StatusHistoryFromJson(json);

  @JsonKey(name: 'from_status')
  final String? fromStatus;
  final String status;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$StatusHistoryToJson(this);
}
