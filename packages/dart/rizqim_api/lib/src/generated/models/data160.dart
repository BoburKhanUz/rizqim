// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch11.dart';
import 'periods.dart';
import 'sales_attributions.dart';
import 'component_types.dart';
import 'guarantee_modes.dart';
import 'guarantee_per.dart';
import 'percent_bases.dart';
import 'event_types.dart';
import 'roles4.dart';
import 'staff7.dart';
import 'rules3.dart';

part 'data160.g.dart';

@JsonSerializable()
class Data160 {
  const Data160({
    required this.branch,
    required this.period,
    required this.periodLabel,
    required this.periods,
    required this.salesAttribution,
    required this.salesAttributionLabel,
    required this.salesAttributions,
    required this.componentTypes,
    required this.guaranteeModes,
    required this.guaranteePer,
    required this.percentBases,
    required this.eventTypes,
    required this.roles,
    required this.staff,
    required this.rules,
  });

  factory Data160.fromJson(Map<String, Object?> json) =>
      _$Data160FromJson(json);

  final Branch11 branch;
  final String period;
  @JsonKey(name: 'period_label')
  final String periodLabel;
  final List<Periods> periods;
  @JsonKey(name: 'sales_attribution')
  final String salesAttribution;
  @JsonKey(name: 'sales_attribution_label')
  final String salesAttributionLabel;
  @JsonKey(name: 'sales_attributions')
  final List<SalesAttributions> salesAttributions;
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

  Map<String, Object?> toJson() => _$Data160ToJson(this);
}
