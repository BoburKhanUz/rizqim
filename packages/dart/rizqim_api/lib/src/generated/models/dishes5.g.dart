// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'dishes5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Dishes5 _$Dishes5FromJson(Map<String, dynamic> json) => Dishes5(
  productId: (json['product_id'] as num?)?.toInt(),
  name: json['name'] as String,
  unit: json['unit'] as String,
  quantityPerPerson: json['quantity_per_person'] as String?,
  quantity: json['quantity'] as String,
  kg: json['kg'] as String?,
);

Map<String, dynamic> _$Dishes5ToJson(Dishes5 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'name': instance.name,
  'unit': instance.unit,
  'quantity_per_person': instance.quantityPerPerson,
  'quantity': instance.quantity,
  'kg': instance.kg,
};
