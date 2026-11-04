// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'devices4.g.dart';

@JsonSerializable()
class Devices4 {
  const Devices4({required this.uuid, required this.type, required this.name});

  factory Devices4.fromJson(Map<String, Object?> json) =>
      _$Devices4FromJson(json);

  final String uuid;
  final String type;
  final String? name;

  Map<String, Object?> toJson() => _$Devices4ToJson(this);
}
