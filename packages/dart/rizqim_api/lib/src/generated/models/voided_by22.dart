// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'voided_by22.g.dart';

@JsonSerializable()
class VoidedBy22 {
  const VoidedBy22({required this.id, required this.name});

  factory VoidedBy22.fromJson(Map<String, Object?> json) =>
      _$VoidedBy22FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$VoidedBy22ToJson(this);
}
