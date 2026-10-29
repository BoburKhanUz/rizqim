// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'roles3.g.dart';

@JsonSerializable()
class Roles3 {
  const Roles3({required this.key, required this.label});

  factory Roles3.fromJson(Map<String, Object?> json) => _$Roles3FromJson(json);

  final String key;
  final String label;

  Map<String, Object?> toJson() => _$Roles3ToJson(this);
}
