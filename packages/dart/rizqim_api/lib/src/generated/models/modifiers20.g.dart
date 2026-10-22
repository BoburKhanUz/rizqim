// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers20.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers20 _$Modifiers20FromJson(Map<String, dynamic> json) => Modifiers20(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  groupName: json['group_name'] as String?,
  quantity: (json['quantity'] as num).toInt(),
  priceDelta: json['price_delta'] as String,
);

Map<String, dynamic> _$Modifiers20ToJson(Modifiers20 instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
