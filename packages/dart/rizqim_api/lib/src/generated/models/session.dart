// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'session.g.dart';

@JsonSerializable()
class Session {
  const Session({
    required this.uuid,
    required this.businessDate,
    required this.clockInAt,
  });

  factory Session.fromJson(Map<String, Object?> json) =>
      _$SessionFromJson(json);

  final String uuid;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'clock_in_at')
  final String clockInAt;

  Map<String, Object?> toJson() => _$SessionToJson(this);
}
