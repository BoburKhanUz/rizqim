// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'modes2.dart';
import 'pool_rules2.dart';
import 'pool_periods2.dart';
import 'roles3.dart';

part 'options2.g.dart';

@JsonSerializable()
class Options2 {
  const Options2({
    required this.modes,
    required this.poolRules,
    required this.poolPeriods,
    required this.roles,
  });

  factory Options2.fromJson(Map<String, Object?> json) =>
      _$Options2FromJson(json);

  final List<Modes2> modes;
  @JsonKey(name: 'pool_rules')
  final List<PoolRules2> poolRules;
  @JsonKey(name: 'pool_periods')
  final List<PoolPeriods2> poolPeriods;
  final List<Roles3> roles;

  Map<String, Object?> toJson() => _$Options2ToJson(this);
}
