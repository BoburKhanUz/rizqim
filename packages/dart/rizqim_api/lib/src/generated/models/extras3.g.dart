// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'extras3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Extras3 _$Extras3FromJson(Map<String, dynamic> json) => Extras3(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  name: json['name'] as String,
  price: json['price'] as String,
  pricing: json['pricing'] as String,
  pricingLabel: json['pricing_label'] as String,
  isActive: json['is_active'] as bool,
  sortOrder: (json['sort_order'] as num).toInt(),
);

Map<String, dynamic> _$Extras3ToJson(Extras3 instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'name': instance.name,
  'price': instance.price,
  'pricing': instance.pricing,
  'pricing_label': instance.pricingLabel,
  'is_active': instance.isActive,
  'sort_order': instance.sortOrder,
};
