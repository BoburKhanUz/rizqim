// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'module_rows.g.dart';

@JsonSerializable()
class ModuleRows {
  const ModuleRows({
    required this.key,
    required this.label,
    required this.description,
    required this.defaultValue,
    required this.override,
    required this.configured,
    required this.planAllows,
    required this.requiredPlan,
    required this.requiredPlanName,
    required this.locked,
    required this.enabled,
  });

  factory ModuleRows.fromJson(Map<String, Object?> json) =>
      _$ModuleRowsFromJson(json);

  final String key;
  final String label;
  final String description;

  /// The name has been replaced because it contains a keyword. Original name: `default`.
  @JsonKey(name: 'default')
  final bool defaultValue;
  final bool? override;
  final bool configured;
  @JsonKey(name: 'plan_allows')
  final bool planAllows;
  @JsonKey(name: 'required_plan')
  final String? requiredPlan;
  @JsonKey(name: 'required_plan_name')
  final String? requiredPlanName;
  final String? locked;
  final bool enabled;

  Map<String, Object?> toJson() => _$ModuleRowsToJson(this);
}
