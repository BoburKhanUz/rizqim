// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'service_fee2.dart';
import 'tables3.dart';

part 'data106.g.dart';

@JsonSerializable()
class Data106 {
  const Data106({
    required this.id,
    required this.branchId,
    required this.name,
    required this.sortOrder,
    required this.isActive,
    required this.serviceFee,
    required this.tables,
  });

  factory Data106.fromJson(Map<String, Object?> json) =>
      _$Data106FromJson(json);

  final int id;
  @JsonKey(name: 'branch_id')
  final int branchId;
  final String name;
  @JsonKey(name: 'sort_order')
  final int sortOrder;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'service_fee')
  final ServiceFee2? serviceFee;
  final List<Tables3> tables;

  Map<String, Object?> toJson() => _$Data106ToJson(this);
}
