// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'lines15.g.dart';

@JsonSerializable()
class Lines15 {
  const Lines15({
    required this.type,
    required this.label,
    required this.quantity,
    required this.unitPrice,
    required this.amount,
  });

  factory Lines15.fromJson(Map<String, Object?> json) =>
      _$Lines15FromJson(json);

  final String type;
  final String label;
  final int quantity;
  @JsonKey(name: 'unit_price')
  final num unitPrice;
  final num amount;

  Map<String, Object?> toJson() => _$Lines15ToJson(this);
}
