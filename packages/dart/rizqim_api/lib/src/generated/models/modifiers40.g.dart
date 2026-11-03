// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers40.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers40 _$Modifiers40FromJson(Map<String, dynamic> json) => Modifiers40(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers40ToJson(Modifiers40 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
