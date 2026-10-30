// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'voided_by21.g.dart';

@JsonSerializable()
class VoidedBy21 {
  const VoidedBy21({required this.id, required this.name});

  factory VoidedBy21.fromJson(Map<String, Object?> json) =>
      _$VoidedBy21FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$VoidedBy21ToJson(this);
}
