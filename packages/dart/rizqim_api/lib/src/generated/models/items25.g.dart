// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'items25.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Items25 _$Items25FromJson(Map<String, dynamic> json) => Items25(
  orderItemId: (json['order_item_id'] as num).toInt(),
  productName: json['product_name'] as String,
  variantName: json['variant_name'] as String?,
  unit: json['unit'] as String,
  quantity: json['quantity'] as num,
  amount: json['amount'] as String,
  discountAmount: json['discount_amount'] as String,
  comboParts: (json['combo_parts'] as List<dynamic>?)
      ?.map((e) => ComboParts5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Items25ToJson(Items25 instance) => <String, dynamic>{
  'order_item_id': instance.orderItemId,
  'product_name': instance.productName,
  'variant_name': instance.variantName,
  'unit': instance.unit,
  'quantity': instance.quantity,
  'amount': instance.amount,
  'discount_amount': instance.discountAmount,
  'combo_parts': instance.comboParts,
};
