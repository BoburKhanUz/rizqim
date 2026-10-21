// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers16.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers16 _$Modifiers16FromJson(Map<String, dynamic> json) => Modifiers16(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers16ToJson(Modifiers16 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
