// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'profiles2.g.dart';

@JsonSerializable()
class Profiles2 {
  const Profiles2({
    required this.key,
    required this.label,
    required this.description,
    required this.mode,
    required this.firstScreen,
    required this.modules,
    required this.locksServiceCharge,
    required this.recommendedPlan,
    required this.stations,
    required this.dishes,
  });

  factory Profiles2.fromJson(Map<String, Object?> json) =>
      _$Profiles2FromJson(json);

  final String key;
  final String label;
  final String description;
  final String mode;
  @JsonKey(name: 'first_screen')
  final String firstScreen;
  final List<String> modules;
  @JsonKey(name: 'locks_service_charge')
  final bool locksServiceCharge;
  @JsonKey(name: 'recommended_plan')
  final String recommendedPlan;
  final List<String> stations;
  final int dishes;

  Map<String, Object?> toJson() => _$Profiles2ToJson(this);
}
