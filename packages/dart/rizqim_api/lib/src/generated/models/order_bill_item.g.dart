// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'order_bill_item.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

OrderBillItem _$OrderBillItemFromJson(Map<String, dynamic> json) =>
    OrderBillItem(
      id: (json['id'] as num).toInt(),
      billId: (json['bill_id'] as num).toInt(),
      orderItemId: (json['order_item_id'] as num).toInt(),
      quantity: json['quantity'] as String,
      amount: json['amount'] as String,
      discountAmount: json['discount_amount'] as String,
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
      updatedAt: json['updated_at'] == null
          ? null
          : DateTime.parse(json['updated_at'] as String),
      bill: json['bill'] == null
          ? null
          : OrderBill.fromJson(json['bill'] as Map<String, dynamic>),
      orderItem: json['order_item'] == null
          ? null
          : OrderItem.fromJson(json['order_item'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$OrderBillItemToJson(OrderBillItem instance) =>
    <String, dynamic>{
      'id': instance.id,
      'bill_id': instance.billId,
      'order_item_id': instance.orderItemId,
      'quantity': instance.quantity,
      'amount': instance.amount,
      'discount_amount': instance.discountAmount,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'bill': instance.bill,
      'order_item': instance.orderItem,
    };
