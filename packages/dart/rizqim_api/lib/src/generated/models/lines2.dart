// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'lines2.g.dart';

@JsonSerializable()
class Lines2 {
  const Lines2({
    required this.type,
    required this.label,
    required this.quantity,
    required this.unitPrice,
    required this.amount,
  });

  factory Lines2.fromJson(Map<String, Object?> json) => _$Lines2FromJson(json);

  final String type;
  final String label;
  final int quantity;
  @JsonKey(name: 'unit_price')
  final num unitPrice;
  final num amount;

  Map<String, Object?> toJson() => _$Lines2ToJson(this);
}
