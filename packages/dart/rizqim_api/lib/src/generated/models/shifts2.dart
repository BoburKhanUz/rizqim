// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'shifts2.g.dart';

@JsonSerializable()
class Shifts2 {
  const Shifts2({
    required this.uuid,
    required this.zNumber,
    required this.status,
  });

  factory Shifts2.fromJson(Map<String, Object?> json) =>
      _$Shifts2FromJson(json);

  final String uuid;
  @JsonKey(name: 'z_number')
  final int? zNumber;
  final String status;

  Map<String, Object?> toJson() => _$Shifts2ToJson(this);
}
