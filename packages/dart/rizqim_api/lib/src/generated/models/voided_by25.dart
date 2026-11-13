// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'voided_by25.g.dart';

@JsonSerializable()
class VoidedBy25 {
  const VoidedBy25({required this.id, required this.name});

  factory VoidedBy25.fromJson(Map<String, Object?> json) =>
      _$VoidedBy25FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$VoidedBy25ToJson(this);
}
