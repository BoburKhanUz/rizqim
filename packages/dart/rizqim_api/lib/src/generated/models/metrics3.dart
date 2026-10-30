// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'metrics3.g.dart';

@JsonSerializable()
class Metrics3 {
  const Metrics3({
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

  factory Metrics3.fromJson(Map<String, Object?> json) =>
      _$Metrics3FromJson(json);

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

  Map<String, Object?> toJson() => _$Metrics3ToJson(this);
}
