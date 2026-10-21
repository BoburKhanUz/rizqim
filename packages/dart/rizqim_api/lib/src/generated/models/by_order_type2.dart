// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_order_type2.g.dart';

@JsonSerializable()
class ByOrderType2 {
  const ByOrderType2({
    required this.type,
    required this.orders,
    required this.revenue,
  });

  factory ByOrderType2.fromJson(Map<String, Object?> json) =>
      _$ByOrderType2FromJson(json);

  final String type;
  final int orders;
  final String revenue;

  Map<String, Object?> toJson() => _$ByOrderType2ToJson(this);
}
