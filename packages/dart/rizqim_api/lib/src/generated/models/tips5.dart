// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'tips5.g.dart';

@JsonSerializable()
class Tips5 {
  const Tips5({
    required this.enabled,
    required this.mode,
    required this.poolRule,
    required this.poolPeriod,
    required this.roles,
  });

  factory Tips5.fromJson(Map<String, Object?> json) => _$Tips5FromJson(json);

  final bool enabled;
  final String mode;
  @JsonKey(name: 'pool_rule')
  final String poolRule;
  @JsonKey(name: 'pool_period')
  final String poolPeriod;
  final List<String> roles;

  Map<String, Object?> toJson() => _$Tips5ToJson(this);
}
