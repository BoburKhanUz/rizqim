// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers21.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers21 _$Modifiers21FromJson(Map<String, dynamic> json) => Modifiers21(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  groupName: json['group_name'] as String?,
  quantity: (json['quantity'] as num).toInt(),
  priceDelta: json['price_delta'] as String,
);

Map<String, dynamic> _$Modifiers21ToJson(Modifiers21 instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
