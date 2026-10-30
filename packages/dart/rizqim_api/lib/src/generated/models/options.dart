// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'modes.dart';
import 'pool_rules.dart';
import 'pool_periods.dart';
import 'roles2.dart';

part 'options.g.dart';

@JsonSerializable()
class Options {
  const Options({
    required this.modes,
    required this.poolRules,
    required this.poolPeriods,
    required this.roles,
  });

  factory Options.fromJson(Map<String, Object?> json) =>
      _$OptionsFromJson(json);

  final List<Modes> modes;
  @JsonKey(name: 'pool_rules')
  final List<PoolRules> poolRules;
  @JsonKey(name: 'pool_periods')
  final List<PoolPeriods> poolPeriods;
  final List<Roles2> roles;

  Map<String, Object?> toJson() => _$OptionsToJson(this);
}
