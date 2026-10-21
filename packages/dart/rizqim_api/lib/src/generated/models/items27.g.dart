// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'items27.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Items27 _$Items27FromJson(Map<String, dynamic> json) => Items27(
  orderItemId: (json['order_item_id'] as num).toInt(),
  productName: json['product_name'] as String,
  variantName: json['variant_name'] as String?,
  unit: json['unit'] as String,
  quantity: json['quantity'] as num,
  amount: json['amount'] as String,
  discountAmount: json['discount_amount'] as String,
);

Map<String, dynamic> _$Items27ToJson(Items27 instance) => <String, dynamic>{
  'order_item_id': instance.orderItemId,
  'product_name': instance.productName,
  'variant_name': instance.variantName,
  'unit': instance.unit,
  'quantity': instance.quantity,
  'amount': instance.amount,
  'discount_amount': instance.discountAmount,
};
