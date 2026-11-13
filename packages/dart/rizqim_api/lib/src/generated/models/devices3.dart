// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'devices3.g.dart';

@JsonSerializable()
class Devices3 {
  const Devices3({required this.uuid, required this.type, required this.name});

  factory Devices3.fromJson(Map<String, Object?> json) =>
      _$Devices3FromJson(json);

  final String uuid;
  final String type;
  final String? name;

  Map<String, Object?> toJson() => _$Devices3ToJson(this);
}
