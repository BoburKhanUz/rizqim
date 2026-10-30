// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'pool_rules.g.dart';

@JsonSerializable()
class PoolRules {
  const PoolRules({required this.key, required this.label});

  factory PoolRules.fromJson(Map<String, Object?> json) =>
      _$PoolRulesFromJson(json);

  final String key;
  final String label;

  Map<String, Object?> toJson() => _$PoolRulesToJson(this);
}
