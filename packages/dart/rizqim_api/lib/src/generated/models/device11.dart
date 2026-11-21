// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'device11.g.dart';

@JsonSerializable()
class Device11 {
  const Device11({
    required this.uuid,
    required this.type,
    required this.name,
    required this.isPrintingDevice,
  });

  factory Device11.fromJson(Map<String, Object?> json) =>
      _$Device11FromJson(json);

  final String uuid;
  final String type;
  final String? name;
  @JsonKey(name: 'is_printing_device')
  final bool isPrintingDevice;

  Map<String, Object?> toJson() => _$Device11ToJson(this);
}
