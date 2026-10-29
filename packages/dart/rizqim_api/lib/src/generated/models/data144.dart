// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch11.dart';
import 'periods.dart';
import 'component_types.dart';
import 'guarantee_modes.dart';
import 'guarantee_per.dart';
import 'percent_bases.dart';
import 'event_types.dart';
import 'roles4.dart';
import 'staff7.dart';
import 'rules3.dart';

part 'data144.g.dart';

@JsonSerializable()
class Data144 {
  const Data144({
    required this.branch,
    required this.period,
    required this.periodLabel,
    required this.periods,
    required this.componentTypes,
    required this.guaranteeModes,
    required this.guaranteePer,
    required this.percentBases,
    required this.eventTypes,
    required this.roles,
    required this.staff,
    required this.rules,
  });

  factory Data144.fromJson(Map<String, Object?> json) =>
      _$Data144FromJson(json);

  final Branch11 branch;
  final String period;
  @JsonKey(name: 'period_label')
  final String periodLabel;
  final List<Periods> periods;
  @JsonKey(name: 'component_types')
  final List<ComponentTypes> componentTypes;
  @JsonKey(name: 'guarantee_modes')
  final List<GuaranteeModes> guaranteeModes;
  @JsonKey(name: 'guarantee_per')
  final List<GuaranteePer> guaranteePer;
  @JsonKey(name: 'percent_bases')
  final List<PercentBases> percentBases;
  @JsonKey(name: 'event_types')
  final List<EventTypes> eventTypes;
  final List<Roles4> roles;
  final List<Staff7> staff;
  final List<Rules3> rules;

  Map<String, Object?> toJson() => _$Data144ToJson(this);
}
