// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'origin_device3.g.dart';

@JsonSerializable()
class OriginDevice3 {
  const OriginDevice3({required this.uuid, required this.name});

  factory OriginDevice3.fromJson(Map<String, Object?> json) =>
      _$OriginDevice3FromJson(json);

  final String uuid;
  final String? name;

  Map<String, Object?> toJson() => _$OriginDevice3ToJson(this);
}
