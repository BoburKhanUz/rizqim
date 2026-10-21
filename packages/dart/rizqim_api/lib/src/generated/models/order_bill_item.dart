// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order_bill.dart';
import 'order_item.dart';

part 'order_bill_item.g.dart';

@JsonSerializable()
class OrderBillItem {
  const OrderBillItem({
    required this.id,
    required this.billId,
    required this.orderItemId,
    required this.quantity,
    required this.amount,
    required this.discountAmount,
    required this.createdAt,
    required this.updatedAt,
    this.bill,
    this.orderItem,
  });

  factory OrderBillItem.fromJson(Map<String, Object?> json) =>
      _$OrderBillItemFromJson(json);

  final int id;
  @JsonKey(name: 'bill_id')
  final int billId;
  @JsonKey(name: 'order_item_id')
  final int orderItemId;
  final String quantity;
  final String amount;
  @JsonKey(name: 'discount_amount')
  final String discountAmount;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final OrderBill? bill;
  @JsonKey(name: 'order_item')
  final OrderItem? orderItem;

  Map<String, Object?> toJson() => _$OrderBillItemToJson(this);
}
