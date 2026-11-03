// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers34.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers34 _$Modifiers34FromJson(Map<String, dynamic> json) => Modifiers34(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers34ToJson(Modifiers34 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
