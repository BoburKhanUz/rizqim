// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'packaging_lines3.g.dart';

@JsonSerializable()
class PackagingLines3 {
  const PackagingLines3({
    required this.ruleId,
    required this.name,
    required this.quantity,
    required this.unit,
    required this.amount,
  });

  factory PackagingLines3.fromJson(Map<String, Object?> json) =>
      _$PackagingLines3FromJson(json);

  @JsonKey(name: 'rule_id')
  final int ruleId;
  final String name;
  final int quantity;
  final String unit;
  final String amount;

  Map<String, Object?> toJson() => _$PackagingLines3ToJson(this);
}
