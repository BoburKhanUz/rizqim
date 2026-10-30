// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'packaging_lines18.g.dart';

@JsonSerializable()
class PackagingLines18 {
  const PackagingLines18({
    required this.ruleId,
    required this.name,
    required this.basis,
    required this.quantity,
    required this.unit,
    required this.unitPrice,
    required this.amount,
    required this.mxik,
    required this.packageCode,
    required this.removed,
    required this.removedReason,
  });

  factory PackagingLines18.fromJson(Map<String, Object?> json) =>
      _$PackagingLines18FromJson(json);

  @JsonKey(name: 'rule_id')
  final int ruleId;
  final String name;
  final String basis;
  final int quantity;
  final String unit;
  @JsonKey(name: 'unit_price')
  final String unitPrice;
  final String amount;
  final String? mxik;
  @JsonKey(name: 'package_code')
  final String? packageCode;
  final bool removed;
  @JsonKey(name: 'removed_reason')
  final String? removedReason;

  Map<String, Object?> toJson() => _$PackagingLines18ToJson(this);
}
