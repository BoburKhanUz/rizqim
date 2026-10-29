// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers25.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers25 _$Modifiers25FromJson(Map<String, dynamic> json) => Modifiers25(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  groupName: json['group_name'] as String?,
  quantity: (json['quantity'] as num).toInt(),
  priceDelta: json['price_delta'] as String,
);

Map<String, dynamic> _$Modifiers25ToJson(Modifiers25 instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
