// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'voided_by9.g.dart';

@JsonSerializable()
class VoidedBy9 {
  const VoidedBy9({required this.id, required this.name});

  factory VoidedBy9.fromJson(Map<String, Object?> json) =>
      _$VoidedBy9FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$VoidedBy9ToJson(this);
}
