// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'voided_by24.g.dart';

@JsonSerializable()
class VoidedBy24 {
  const VoidedBy24({required this.id, required this.name});

  factory VoidedBy24.fromJson(Map<String, Object?> json) =>
      _$VoidedBy24FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$VoidedBy24ToJson(this);
}
