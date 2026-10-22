// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'layout4.dart';

part 'data52.g.dart';

@JsonSerializable()
class Data52 {
  const Data52({
    required this.id,
    required this.branchId,
    required this.hallId,
    required this.name,
    required this.kind,
    required this.seats,
    required this.layout,
    required this.sortOrder,
    required this.isActive,
    required this.minSpend,
    required this.minSpendMode,
  });

  factory Data52.fromJson(Map<String, Object?> json) => _$Data52FromJson(json);

  final int id;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'hall_id')
  final int hallId;
  final String name;
  final String kind;
  final int? seats;
  final Layout4 layout;
  @JsonKey(name: 'sort_order')
  final int sortOrder;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'min_spend')
  final String? minSpend;
  @JsonKey(name: 'min_spend_mode')
  final String minSpendMode;

  Map<String, Object?> toJson() => _$Data52ToJson(this);
}
