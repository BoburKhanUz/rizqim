// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'settings2.g.dart';

@JsonSerializable()
class Settings2 {
  const Settings2({
    required this.branchId,
    required this.enabled,
    required this.mode,
    required this.poolRule,
    required this.poolPeriod,
    required this.roles,
    required this.roleWeights,
    required this.updatedAt,
  });

  factory Settings2.fromJson(Map<String, Object?> json) =>
      _$Settings2FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final bool enabled;
  final String mode;
  @JsonKey(name: 'pool_rule')
  final String poolRule;
  @JsonKey(name: 'pool_period')
  final String poolPeriod;
  final List<String> roles;
  @JsonKey(name: 'role_weights')
  final Map<String, String> roleWeights;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;

  Map<String, Object?> toJson() => _$Settings2ToJson(this);
}
