// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'device.g.dart';

@JsonSerializable()
class Device {
  const Device({required this.uuid, required this.name, required this.type});

  factory Device.fromJson(Map<String, Object?> json) => _$DeviceFromJson(json);

  final String uuid;
  final String? name;
  final String type;

  Map<String, Object?> toJson() => _$DeviceToJson(this);
}
