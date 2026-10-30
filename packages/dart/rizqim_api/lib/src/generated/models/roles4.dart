// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'roles4.g.dart';

@JsonSerializable()
class Roles4 {
  const Roles4({required this.value, required this.label});

  factory Roles4.fromJson(Map<String, Object?> json) => _$Roles4FromJson(json);

  final String value;
  final String label;

  Map<String, Object?> toJson() => _$Roles4ToJson(this);
}
