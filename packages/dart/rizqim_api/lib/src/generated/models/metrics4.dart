// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'metrics4.g.dart';

@JsonSerializable()
class Metrics4 {
  const Metrics4({
    required this.shifts,
    required this.minutes,
    required this.bills,
    required this.sales,
    required this.serviceCharge,
    required this.events,
    required this.tips,
    required this.uncountedSessions,
    required this.rules,
    required this.ownRule,
  });

  factory Metrics4.fromJson(Map<String, Object?> json) =>
      _$Metrics4FromJson(json);

  final int shifts;
  final int minutes;
  final int bills;
  final String sales;
  @JsonKey(name: 'service_charge')
  final String serviceCharge;
  final int events;
  final String tips;
  @JsonKey(name: 'uncounted_sessions')
  final int uncountedSessions;
  final List<String> rules;
  @JsonKey(name: 'own_rule')
  final bool ownRule;

  Map<String, Object?> toJson() => _$Metrics4ToJson(this);
}
