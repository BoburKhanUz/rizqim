// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers43.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers43 _$Modifiers43FromJson(Map<String, dynamic> json) => Modifiers43(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers43ToJson(Modifiers43 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
