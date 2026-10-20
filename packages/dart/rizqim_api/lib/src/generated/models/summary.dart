// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'summary.g.dart';

@JsonSerializable()
class Summary {
  const Summary({
    required this.ordersCount,
    required this.deliveredCount,
    required this.canceledCount,
    required this.refundedCount,
    required this.activeCount,
    required this.revenue,
    required this.averageCheck,
  });

  factory Summary.fromJson(Map<String, Object?> json) =>
      _$SummaryFromJson(json);

  @JsonKey(name: 'orders_count')
  final int ordersCount;
  @JsonKey(name: 'delivered_count')
  final int deliveredCount;
  @JsonKey(name: 'canceled_count')
  final int canceledCount;
  @JsonKey(name: 'refunded_count')
  final int refundedCount;
  @JsonKey(name: 'active_count')
  final int activeCount;
  final num revenue;
  @JsonKey(name: 'average_check')
  final num averageCheck;

  Map<String, Object?> toJson() => _$SummaryToJson(this);
}
