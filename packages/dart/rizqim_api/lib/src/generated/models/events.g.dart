// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'events.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Events _$EventsFromJson(Map<String, dynamic> json) => Events(
  uid: json['uid'] as String,
  name: json['name'] as String,
  at: (json['at'] as num).toInt(),
  storeId: (json['store_id'] as num?)?.toInt(),
  productId: (json['product_id'] as num?)?.toInt(),
  categoryId: (json['category_id'] as num?)?.toInt(),
  props: json['props'] as Map<String, dynamic>?,
);

Map<String, dynamic> _$EventsToJson(Events instance) => <String, dynamic>{
  'uid': instance.uid,
  'name': instance.name,
  'at': instance.at,
  'store_id': instance.storeId,
  'product_id': instance.productId,
  'category_id': instance.categoryId,
  'props': instance.props,
};
