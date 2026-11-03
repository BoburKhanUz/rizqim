// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'stations4.g.dart';

@JsonSerializable()
class Stations4 {
  const Stations4({
    required this.id,
    required this.branchId,
    required this.name,
    required this.kind,
    required this.kindLabel,
    required this.sortOrder,
    required this.isActive,
    required this.showOnKds,
    required this.targetSeconds,
  });

  factory Stations4.fromJson(Map<String, Object?> json) =>
      _$Stations4FromJson(json);

  final int id;
  @JsonKey(name: 'branch_id')
  final int branchId;
  final String name;
  final String kind;
  @JsonKey(name: 'kind_label')
  final String kindLabel;
  @JsonKey(name: 'sort_order')
  final int sortOrder;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'show_on_kds')
  final bool showOnKds;
  @JsonKey(name: 'target_seconds')
  final int? targetSeconds;

  Map<String, Object?> toJson() => _$Stations4ToJson(this);
}
