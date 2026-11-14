// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'event10.g.dart';

@JsonSerializable()
class Event10 {
  const Event10({
    required this.uuid,
    required this.status,
    required this.version,
    required this.orderUuid,
  });

  factory Event10.fromJson(Map<String, Object?> json) =>
      _$Event10FromJson(json);

  final String uuid;
  final String status;
  final int version;
  @JsonKey(name: 'order_uuid')
  final String? orderUuid;

  Map<String, Object?> toJson() => _$Event10ToJson(this);
}
