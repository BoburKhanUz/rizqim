// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'banquet_lines3.g.dart';

@JsonSerializable()
class BanquetLines3 {
  const BanquetLines3({
    required this.kind,
    required this.name,
    required this.quantity,
    required this.unit,
    required this.amount,
  });

  factory BanquetLines3.fromJson(Map<String, Object?> json) =>
      _$BanquetLines3FromJson(json);

  final String kind;
  final String name;
  final String quantity;
  final String unit;
  final String amount;

  Map<String, Object?> toJson() => _$BanquetLines3ToJson(this);
}
