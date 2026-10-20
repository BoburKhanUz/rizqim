// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_order_type.g.dart';

@JsonSerializable()
class ByOrderType {
  const ByOrderType({
    required this.type,
    required this.orders,
    required this.revenue,
  });

  factory ByOrderType.fromJson(Map<String, Object?> json) =>
      _$ByOrderTypeFromJson(json);

  final String type;
  final int orders;
  final num revenue;

  Map<String, Object?> toJson() => _$ByOrderTypeToJson(this);
}
