// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'event12.g.dart';

@JsonSerializable()
class Event12 {
  const Event12({
    required this.uuid,
    required this.status,
    required this.version,
    required this.orderUuid,
    required this.startedAt,
  });

  factory Event12.fromJson(Map<String, Object?> json) =>
      _$Event12FromJson(json);

  final String uuid;
  final String status;
  final int version;
  @JsonKey(name: 'order_uuid')
  final String? orderUuid;
  @JsonKey(name: 'started_at')
  final String? startedAt;

  Map<String, Object?> toJson() => _$Event12ToJson(this);
}
