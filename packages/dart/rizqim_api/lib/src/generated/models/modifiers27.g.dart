// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers27.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers27 _$Modifiers27FromJson(Map<String, dynamic> json) => Modifiers27(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  groupName: json['group_name'] as String?,
  quantity: (json['quantity'] as num).toInt(),
  priceDelta: json['price_delta'] as String,
);

Map<String, dynamic> _$Modifiers27ToJson(Modifiers27 instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
