// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'origin_device.g.dart';

@JsonSerializable()
class OriginDevice {
  const OriginDevice({required this.uuid, required this.name});

  factory OriginDevice.fromJson(Map<String, Object?> json) =>
      _$OriginDeviceFromJson(json);

  final String uuid;
  final String? name;

  Map<String, Object?> toJson() => _$OriginDeviceToJson(this);
}
