// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'origin_device2.g.dart';

@JsonSerializable()
class OriginDevice2 {
  const OriginDevice2({required this.uuid, required this.name});

  factory OriginDevice2.fromJson(Map<String, Object?> json) =>
      _$OriginDevice2FromJson(json);

  final String uuid;
  final String? name;

  Map<String, Object?> toJson() => _$OriginDevice2ToJson(this);
}
