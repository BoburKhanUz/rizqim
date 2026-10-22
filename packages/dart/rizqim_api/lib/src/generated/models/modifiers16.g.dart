// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers16.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers16 _$Modifiers16FromJson(Map<String, dynamic> json) => Modifiers16(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  groupName: json['group_name'] as String?,
  quantity: (json['quantity'] as num).toInt(),
  priceDelta: json['price_delta'] as String,
);

Map<String, dynamic> _$Modifiers16ToJson(Modifiers16 instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
