// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'pool_rules2.g.dart';

@JsonSerializable()
class PoolRules2 {
  const PoolRules2({required this.key, required this.label});

  factory PoolRules2.fromJson(Map<String, Object?> json) =>
      _$PoolRules2FromJson(json);

  final String key;
  final String label;

  Map<String, Object?> toJson() => _$PoolRules2ToJson(this);
}
