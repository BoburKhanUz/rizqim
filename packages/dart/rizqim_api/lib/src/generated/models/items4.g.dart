// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'items4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Items4 _$Items4FromJson(Map<String, dynamic> json) => Items4(
  orderItemId: (json['order_item_id'] as num).toInt(),
  productName: json['product_name'] as String,
  variantName: json['variant_name'] as String?,
  unit: json['unit'] as String,
  quantity: json['quantity'] as num,
  amount: json['amount'] as String,
  discountAmount: json['discount_amount'] as String,
);

Map<String, dynamic> _$Items4ToJson(Items4 instance) => <String, dynamic>{
  'order_item_id': instance.orderItemId,
  'product_name': instance.productName,
  'variant_name': instance.variantName,
  'unit': instance.unit,
  'quantity': instance.quantity,
  'amount': instance.amount,
  'discount_amount': instance.discountAmount,
};
