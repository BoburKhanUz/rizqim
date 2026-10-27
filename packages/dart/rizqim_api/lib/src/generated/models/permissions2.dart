// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'permissions2.g.dart';

@JsonSerializable()
class Permissions2 {
  const Permissions2({
    required this.key,
    required this.label,
    required this.group,
  });

  factory Permissions2.fromJson(Map<String, Object?> json) =>
      _$Permissions2FromJson(json);

  final String key;
  final String label;
  final String group;

  Map<String, Object?> toJson() => _$Permissions2ToJson(this);
}
