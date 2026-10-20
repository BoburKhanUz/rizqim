// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order.dart';
import 'order_status.dart';
import 'user.dart';

part 'order_status_history.g.dart';

@JsonSerializable()
class OrderStatusHistory {
  const OrderStatusHistory({
    required this.id,
    required this.orderId,
    required this.userId,
    required this.status,
    required this.notes,
    required this.createdAt,
    required this.updatedAt,
    this.order,
    this.user,
  });

  factory OrderStatusHistory.fromJson(Map<String, Object?> json) =>
      _$OrderStatusHistoryFromJson(json);

  final int id;
  @JsonKey(name: 'order_id')
  final int orderId;
  @JsonKey(name: 'user_id')
  final int? userId;
  final OrderStatus status;
  final String? notes;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final Order? order;
  final User? user;

  Map<String, Object?> toJson() => _$OrderStatusHistoryToJson(this);
}
