// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'service_fee3.dart';
import 'tables4.dart';

part 'data102.g.dart';

@JsonSerializable()
class Data102 {
  const Data102({
    required this.id,
    required this.branchId,
    required this.name,
    required this.sortOrder,
    required this.isActive,
    required this.serviceFee,
    required this.tables,
  });

  factory Data102.fromJson(Map<String, Object?> json) =>
      _$Data102FromJson(json);

  final int id;
  @JsonKey(name: 'branch_id')
  final int branchId;
  final String name;
  @JsonKey(name: 'sort_order')
  final int sortOrder;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'service_fee')
  final ServiceFee3? serviceFee;
  final List<Tables4> tables;

  Map<String, Object?> toJson() => _$Data102ToJson(this);
}
