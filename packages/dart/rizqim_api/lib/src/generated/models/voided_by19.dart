// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'voided_by19.g.dart';

@JsonSerializable()
class VoidedBy19 {
  const VoidedBy19({required this.id, required this.name});

  factory VoidedBy19.fromJson(Map<String, Object?> json) =>
      _$VoidedBy19FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$VoidedBy19ToJson(this);
}
