// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'role2.dart';

part 'roles3.g.dart';

@JsonSerializable()
class Roles3 {
  const Roles3({
    required this.role,
    required this.label,
    required this.editable,
  });

  factory Roles3.fromJson(Map<String, Object?> json) => _$Roles3FromJson(json);

  final Role2 role;
  final String label;
  final bool editable;

  Map<String, Object?> toJson() => _$Roles3ToJson(this);
}
